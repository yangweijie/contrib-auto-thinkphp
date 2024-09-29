<?php

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP;
class PreInstall
{
    public static function checkComposerJson(){
        $app_path = realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);
        $composer_json = $app_path.DIRECTORY_SEPARATOR.'composer.json';
        if(is_file($composer_json)){
            $json = file_get_contents($composer_json);
            $arr = json_decode($json, true);
            if(!isset($arr['extra'])){
                throw new \Exception("no extra section");
            }else{
                if(isset($arr['extra']['include_files'])){
                    $has = false;
                    foreach($arr['extra']['include_files'] as $include_file){
                        if($include_file == 'vendor/yangweijie/opentelemetry-auto-thinkphp/src/polyfill/log/driver/File.php'){
                            $has = true;
                        }
                    }
                    if(!$has){
                        throw new \Exception("log driver File polyfill not be setted correct !");
                    }
                }else{
                    throw new \Exception("no [include files] setting in extra section");
                }
            }
        }else{
            throw new \Exception("Composer.json file does not exist");
        }
        return true;
    }
}