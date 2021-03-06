<?php
namespace Framework\Core;

abstract class Common
{
    private static $_instance=array();

    /**
     * \Parith\Lib\File::factory();
     *
     * @static
     * @return object
     */
    public static function factory()
    {
        return static::getInstance(get_called_class(),func_get_args());
    }

    /**
     * @param $class
     * @param array $args
     * @param null $key
     */
    public static function getInstance($class,$args=array(),$key=null)
    {
        $key or $key=$class;
        $obj=& self::$_instance[$key];
        if($obj){
            return $obj;
        }
        switch(count($args)){
            case 1:
                return $obj=new $class($args[0]);
            case 2:
                return $obj=new $class($args[0],$args[1]);
            case 3:
                return $obj=new $class($args[0],$args[1],$args[2]);
            case 4:
                return $obj=new $class($args[0],$args[1],$args[2],$args[3]);
            default:
                return $obj=new $class();
        }
    }
}


