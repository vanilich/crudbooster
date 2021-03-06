<?php 

namespace Unisharp\Laravelfilemanager\middleware;

use Closure;
use crocodicstudio\crudbooster\helpers\CRUDBooster;

class MultiUser
{
    public function handle($request, Closure $next)
    {
    	if (\Config::get('lfm.allow_multi_user') === true) {
    		$slug = \Config::get('lfm.user_field');

            $new_working_dir = '/' . auth('cbAdmin')->id();

	        $previous_dir = $request->input('working_dir');

	        if ($previous_dir == null) {
	            $request->merge(['working_dir' => $new_working_dir]);
	        } elseif (! $this->validDir($previous_dir)) {
	            $request->replace(['working_dir' => $new_working_dir]);
	        }
	    }

        return $next($request);
    }

    private function validDir($previous_dir)
    {
    	if (starts_with($previous_dir, '/' . \Config::get('lfm.shared_folder_name'))) {
    		return true;
        }

        if (starts_with($previous_dir, '/' . auth('cbAdmin')->id() )) {
        	return true;
        }

        return false;
    }
}
