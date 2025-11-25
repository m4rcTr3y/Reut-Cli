<?php
declare(strict_types=1);

namespace Reut\Auth;

use Reut\Middleware\JwtAuth;
use Slim\App;

abstract class Auth{

    protected $app;
    protected $authMiddleware;

    public function __construct(App $app,$config){
        $this->app = $app;
        $this->authMiddleware = new JwtAuth($config); 

        $this->app->add($this->authMiddleware);
        
        $this->genRoutes();
    }

    abstract protected function genRoutes();


}