<?php

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks;

class Reflect
{
    public static function getClassProperty($obj, $name){
        $property = new \ReflectionProperty($obj, $name);
        if(!$property->isPublic() &&  !$property->isStatic()){
            $property->setAccessible(true);
        }
        return $property->getValue($obj);
    }
}