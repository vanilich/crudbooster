<?php

namespace crocodicstudio\crudbooster\controllers;

use crocodicstudio\crudbooster\helpers\DbInspector;
use crocodicstudio\crudbooster\helpers\CRUDBooster;

class FormValidator
{
    private $table;

    private $ctrl;

    public function validate($id = null, $form, $ctrl)
    {
        $this->ctrl = $ctrl;
        $this->table = $ctrl->table;
        $rules = $this->getRules($id, $form);

        $validator = \Validator::make(request()->all(), $rules);

        if (! $validator->fails()) {
            return null;
        }

        $this->sendFailedValidationResponse($validator);
    }

    /**
     * @param $id
     * @param $form
     * @return mixed
     */
    private function getRules($id, $form)
    {
        $cmpPath = CbComponentsPath();
        $rules = [];
        foreach ($form as $formInput) {
            $name = $formInput['name'];
            if (! $name) {
                continue;
            }

            $ai = [];
            if ($formInput['required'] && ! \Request::hasFile($name)) {
                $ai[] = 'required';
            }

            $hookValidationPath = $cmpPath.$formInput['type'].DIRECTORY_SEPARATOR.'hookInputValidation.php';
            if (file_exists($hookValidationPath)) {
                require_once($hookValidationPath);
            }
            unset($hookValidationPath);

            if (@$formInput['validation']) {
                $rules[$name] = $this->parseValidationRules($id, $formInput);
            } else {
                $rules[$name] = implode('|', $ai);
            }
        }

        return $rules;
    }

    /**
     * @param $id
     * @param $formInput
     * @return array
     */
    private function parseValidationRules($id, $formInput)
    {
        $exp = explode('|', $formInput['validation']);

        $uniqueRules = array_filter($exp, function($item){
            return starts_with($item, 'unique');
        });

        foreach ($uniqueRules as &$validationItem) {
            $parseUnique = explode(',', str_replace('unique:', '', $validationItem));
            $uniqueTable = ($parseUnique[0]) ?: $this->table;
            $uniqueColumn = ($parseUnique[1]) ?: $formInput['name'];
            $uniqueIgnoreId = ($parseUnique[2]) ?: (($id) ?: '');

            //Make sure table name
            $uniqueTable = CRUDBooster::parseSqlTable($uniqueTable)['table'];

            //Rebuild unique rule
            $uniqueRebuild = [];
            $uniqueRebuild[] = $uniqueTable;
            $uniqueRebuild[] = $uniqueColumn;

            if ($uniqueIgnoreId) {
                $uniqueRebuild[] = $uniqueIgnoreId;
            } else {
                $uniqueRebuild[] = 'NULL';
            }

            //Check whether deleted_at exists or not
            if (\Schema::hasColumn($uniqueTable, 'deleted_at')) {
                $uniqueRebuild[] = DbInspector::findPK($uniqueTable);
                $uniqueRebuild[] = 'deleted_at';
                $uniqueRebuild[] = 'NULL';
            }
            $uniqueRebuild = array_filter($uniqueRebuild);
            $validationItem = 'unique:'.implode(',', $uniqueRebuild);
        }

        return implode('|', $exp);
    }

    /**
     * @param $validator
     */
    private function sendFailedValidationResponse($validator)
    {
        $message = $validator->messages();
        $msg = [
            'message' => cbTrans('alert_validation_error', ['error' => implode(', ', $message->all())]),
            'message_type' => 'warning',
        ];

        if (\Request::ajax()) {
            $resp = response()->json($msg);
            sendAndTerminate($resp);
        }
        sendAndTerminate(redirect()->back()->with("errors", $message)->with($msg)->withnput());
    }
}