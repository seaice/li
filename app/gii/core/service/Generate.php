<?php
namespace gii\core\service;

use Li\Db;
use Li\Service;

class Generate extends Service
{
    public static function service($className = __CLASS__)
    {
        return parent::service($className);
    }

    public function textDiff($lines1, $lines2)
    {
        require_once(PATH_APP.'core'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'Text'.DIRECTORY_SEPARATOR.'Diff.php');
        // require_once(PATH_APP.'core'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'Text'.DIRECTORY_SEPARATOR.'Diff'.DIRECTORY_SEPARATOR.'Renderer.php');
        require_once(PATH_APP.'core'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'Text'.DIRECTORY_SEPARATOR.'Diff'.DIRECTORY_SEPARATOR.'Renderer'.DIRECTORY_SEPARATOR.'unified.php');
        require_once(PATH_APP.'core'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'Text'.DIRECTORY_SEPARATOR.'Diff'.DIRECTORY_SEPARATOR.'Renderer'.DIRECTORY_SEPARATOR.'context.php');
        require_once(PATH_APP.'core'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'Text'.DIRECTORY_SEPARATOR.'Diff'.DIRECTORY_SEPARATOR.'Renderer'.DIRECTORY_SEPARATOR.'inline.php');

        if (is_string($lines1)) {
            $lines1=explode("\n", $lines1);
        }
        if (is_string($lines2)) {
            $lines2=explode("\n", $lines2);
        }

        $diff = new \Text_Diff('auto', array($lines1, $lines2));
        $renderer = new \Text_Diff_Renderer_inline();
        return $renderer->render($diff);
    }

    public function getColumns($project, $env, $db, $table)
    {
        if (empty($project)
            || empty($env)
            || empty($db)
            || empty($table)
            ) {
            return false;
        }

        require PATH_BASE . 'app' . DIRECTORY_SEPARATOR . $project . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $env . DIRECTORY_SEPARATOR . 'database.php';

        Db::db()->initByConfig($db, $config['database'][$db]);

        $db = Db::db()->$db;
        // $column = $db->query('DESC `'.$table.'`');
        // $column = $db->query('SELECT * FROM information_schema.columns WHERE table_name = "'.$table.'"');
        $column = $db->query('SHOW FULL COLUMNS FROM `'.$table.'`');

        foreach ($column as &$value) {
            $validate = [];
            $match = null;

            if ($value['Null']=='NO' && $value['Default']===null && $value['Extra']!='auto_increment') {
                $validate[] = 'required';
            }

            if (strpos($value['Type'], 'int')!==false && $value['Extra']!='auto_increment') {
                $validate[] = 'integer';
            } elseif (strpos($value['Type'], 'varchar')!==false) {
                preg_match('/varchar\((\d+)\)/i', $value['Type'], $match);
                if (!empty($match[1])) {
                    $validate[] = 'max:' . $match[1];
                }
            } elseif (strpos($value['Type'], 'char')!==false) {
                preg_match('/char\((\d+)\)/i', $value['Type'], $match);
                if (!empty($match[1])) {
                    $validate[] = 'max:' . $match[1];
                }
            } elseif (strpos($value['Type'], 'datetime')!==false) {
                $validate[] = 'date';
                $validate[] = 'date_format:Y-m-d H:i:s';
            } elseif (strpos($value['Type'], 'date')!==false) {
                $validate[] = 'date';
                $validate[] = 'date_format:Y-m-d';
            }

            $value['validate'] = implode('|', $validate);
            $value['dataType'] = $this->extractType($value['Type']);
        }
        
        return $column;
    }

    private function extractType($dbType)
    {
        if (stripos($dbType, 'int')!==false && stripos($dbType, 'unsigned int')===false) {
            $type='integer';
        } elseif (stripos($dbType, 'bool')!==false) {
            $type='boolean';
        } elseif (preg_match('/(real|floa|doub)/i', $dbType)) {
            $type='double';
        } else {
            $type='string';
        }

        return $type;
    }

    /**
     * 处理table名称
     */
    public function transName($name, $ucfrist = true)
    {
        $newName = '';
        for ($i=0; $i<strlen($name); $i++) {
            if ($name[$i] == '_') {
                $name[$i+1] = strtoupper($name[$i+1]);
            } else {
                $newName .= $name[$i];
            }
        }
        if ($ucfrist) {
            return ucfirst($newName);
        }
        return $newName;
    }

    public function getModelFile($project, $env, $db, $table)
    {
        $file=null;
        $model = $this->transName($table);

        $file['file'] = 'app' . DIRECTORY_SEPARATOR . $project . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . $model .'.php';
        $file['path'] = PATH_BASE . $file['file'];
        $file['url'] = url('generate/modelCode/project/'.$project.'/env/'.$env.'/db/'.$db.'/table/'.$table);
        $file['exist'] = false;
        $file['type'] = 'model';
        $file['value'] = 'model/'.$project.'/'.$env.'/'.$db.'/'.$table;
        if (file_exists($file['path'])) {
            $file['exist'] = true;
        }

        return $file;
    }

    /**
     * @param string $project project name
     * @param string $controller controller name
     */
    public function getControllerFile($project, $controller, $env = null, $db = null, $table = null)
    {
        $file=null;
        $file['file'] = 'app'.DIRECTORY_SEPARATOR.$project.DIRECTORY_SEPARATOR.'core'. DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . ucfirst($controller) .'Controller.php';
        $file['path'] = PATH_BASE.$file['file'];
        $file['url'] = url('/generate/controllerCode/project/'.$project.'/controller/'.$controller.'/env/'.$env.'/db/'.$db.'/table/'.$table);
        $file['exist'] = false;
        $file['type'] = 'controller';
        $file['value'] = 'controller/'.$project.'/'.$controller.'/'.$env.'/'.$db.'/'.$table;
        if (file_exists($file['path'])) {
            $file['exist'] = true;
        }

        return $file;
    }

    public function getTemplateFile($project, $env, $db, $table, $action)
    {
        $file=null;
        $controller =  $this->transName($table, false);

        $file['file'] = 'app'.DIRECTORY_SEPARATOR.$project.DIRECTORY_SEPARATOR.'core'. DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR . $controller . DIRECTORY_SEPARATOR. $action.'.html';
        $file['path'] = PATH_BASE.$file['file'] ;
        $file['url'] = url('/generate/'.$action.'Code/project/'.$project.'/env/'.$env.'/db/'.$db.'/table/'.$table);
        $file['exist'] = false;
        $file['type'] = 'template';
        $file['name'] = $action;
        $file['value'] = 'template/'.$project.'/'.$env.'/'.$db.'/'.$table.'/'.$action;
        if (file_exists($file['path'])) {
            $file['exist'] = true;
        }
        return $file;
    }


    public function getProject()
    {
        $project = array();

        if ($handle = opendir(PATH_BASE . 'app')) {
            /* 这是正确地遍历目录方法 */
            while (false !== ($file = readdir($handle))) {
                if ($file != '..'
                    && $file != '.'
                    && $file != 'gii'
                ) {
                    $project[] = $file;
                }
            }

            closedir($handle);
        }

        return $project;
    }

    public function getPk($columns)
    {
        foreach ($columns as $column) {
            if ($column['Key'] == 'PRI') {
                $pk[] = $column['Field'];
            }
        }

        if (count($pk) == 1) {
            return '"'.$pk[0].'"';
        } elseif (count($pk) > 1) {
            $pk = implode('","', $pk);

            return 'array("'.$pk.'")';
        }
    }

    /**
     * @param object $obj controller class
     * @param string $project project name
     * @param string $env env name
     * @param string $db db name
     * @param string $table table name
     */
    public function getModelCode($obj, $project, $env, $db, $table)
    {
        $obj->assign('model', $this->transName($table));
        $obj->assign('project', $project);
        $obj->assign('table', $table);
        $columns = $this->getColumns($project, $env, $db, $table);
        $obj->assign('columns', $columns);
        $obj->assign('pk', $this->getPk($columns));
        $html = $obj->fetch('template/model');

        return $html;
    }

    /**
     * @param object $obj controller class
     * @param string $project project name
     * @param string $controller controller name
     */
    public function getControllerCode($obj, $project, $controller, $env = null, $db = null, $table = null)
    {
        if (!empty($env)
            && !empty($db)
            && !empty($table)
        ) {
            $obj->assign('columns', $this->getColumns($project, $env, $db, $table));
        }

        $obj->assign('project', $project);
        $obj->assign('controller', $controller);
        $html=$obj->fetch('template/controller');
        return $html;
    }

    /**
     * @param object $obj controller class
     * @param string $project project name
     * @param string $controller controller name
     */
    public function getAdminCode($obj, $project, $controller)
    {
        $obj->assign('controller', $controller);
        $html=$obj->fetch('template/template_admin');
        return $html;
    }
    /**
     * @param object $obj controller class
     * @param string $project project name
     * @param string $controller controller name
     */
    public function getCreateCode($obj, $project, $controller)
    {
        $obj->assign('controller', $controller);
        $html=$obj->fetch('template/template_create');
        return $html;
    }
    /**
     * @param object $obj controller class
     * @param string $project project name
     * @param string $controller controller name
     */
    public function getUpdateCode($obj, $project, $controller)
    {
        $obj->assign('controller', $controller);
        $html=$obj->fetch('template/template_update');
        return $html;
    }
    /**
     * @param object $obj controller class
     * @param string $project project name
     * @param string $controller controller name
     */
    public function getFormCode($obj, $project, $env, $db, $table)
    {
        $obj->assign('columns', $this->getColumns($project, $env, $db, $table));
        $html=$obj->fetch('template/template_form');

        return $html;
        // $obj->assign('controller', $controller);
        // $html=$obj->fetch('template/template_update');
        // return $html;
    }
}
