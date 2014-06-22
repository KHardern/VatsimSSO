<?php namespace Vatsim\OAuth\Facades;

use Illuminate\Support\Facades\Facade;

class SSO extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'vatsimoauth'; }

}