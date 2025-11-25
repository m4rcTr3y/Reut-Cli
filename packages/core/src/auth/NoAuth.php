<?php
declare(strict_types=1);

namespace Reut\Auth;

use Reut\Middleware\JwtAuth;
use Slim\App;

abstract class NoAuth{

    protected $app;

    public function __construct(App $app){
        $this->app = $app;
        $this->genRoutes();
    }

    abstract protected function genRoutes();


}