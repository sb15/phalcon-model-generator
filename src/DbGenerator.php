<?php

namespace Sb;

use Sb\Phalcon\Db\Scheme;
use Sb\PhpClassGenerator\AbstractClass;
use Sb\PhpClassGenerator\AbstractClassField;
use Sb\PhpClassGenerator\AbstractClassMethod;
use Sb\PhpClassGenerator\AbstractMethodParam;
use Sb\Utils as SbUtils;

class DbGenerator
{
    /**
     * @var \Phalcon\DI\FactoryDefault
     */
    private $di;

    private $modelDir;
    private $entityDir;

    private $entityNamespace = "Entity";
    private $entityNamespaceGenerated = "Entity\\Generated";
    private $modelNamespace = "Model";

    private $relations = [];

    public function __construct($di)
    {
        $this->di = $di;
    }

    public function setModelDir($modelDir)
    {
        $this->modelDir = $modelDir;
    }

    public function setEntityDir($entityDir)
    {
        $this->entityDir = $entityDir;
    }

    public function generateCommonMethods()
    {
        if (!is_file($this->modelDir . "\\BasicModel.php")) {
            $basicModel = new AbstractClass('BasicModel');
            $basicModel->setNamespace('Model');
            $basicModel->addUse('Phalcon\\Di\\Injectable');
            $basicModel->setExtends('Injectable');
            $basicModel->addDocBlock('@property \\Sb\\Phalcon\\Model\\Repository modelsRepository');
            $basicModel->addDocBlock('@property \\Sb\\Phalcon\\Form\\Repository formsRepository');

            file_put_contents($this->modelDir . "\\BasicModel.php", $basicModel);
        }
    }

    public function prepareRef($ref, $field = 'column')
    {
        $doubleRef = [];
        $models = [];

        foreach ($ref as $k => $refData) {
            if (in_array($refData['model'], $models)) {
                $doubleRef[] = $refData['model'];
            } else {
                $models[] = $refData['model'];
            }
        }

        foreach ($ref as $k => $refData) {
            if (in_array($refData['model'], $doubleRef)) {

                $temp = preg_replace("#_?id_#uis", "-", $refData[$field]);
                $temp = preg_replace("#_id_?#uis", "-", $temp);
                $temp = ucfirst(\Phalcon\Text::camelize($temp));
                $ref[$k]['alias'] = $temp;

            } else {
                $ref[$k]['alias'] = $refData['model'];
            }
        }

        return $ref;
    }

    public function generate($options = array())
    {

        $dbScheme = new Scheme($this->di);
        $tables = $dbScheme->getScheme($options);
        $this->relations = [];

        //var_dump($tables);

        if (!is_dir($this->entityDir . "\\" . 'Generated')) {
            mkdir($this->entityDir . "\\" . 'Generated', 0777, true);
        }

        $this->generateCommonMethods();

        foreach ($tables as $table) {
            $tableClass = new AbstractClass($table['model']);
            $tableClass->addUse('\\Phalcon\\Mvc\\Model');
            $tableClass->setExtends('Model');
            $tableClass->setNamespace($this->entityNamespaceGenerated);

            $tableClassChild = new AbstractClass($table['model']);
            $tableClassChild->setExtends("Generated\\" . $table['model']);
            $tableClassChild->setNamespace($this->entityNamespace);

            foreach ($table['columns'] as $field) {
                $fieldField = new AbstractClassField($field);
                $fieldField->setScope('protected');
                $tableClass->addField($fieldField);
            }

            $getSourceMethod = new AbstractClassMethod("getSource");
            $getSourceMethod->setScope("public");
            $getSourceMethod->addContentLine("return '{$table['name']}';");
            $tableClass->addMethod($getSourceMethod);

            /*$onConstructMethod = new AbstractClassMethod("onConstruct");
            $tableClass->addMethod($onConstructMethod);

            $beforeSaveMethod = new AbstractClassMethod("beforeSave");
            $beforeSaveMethod->addContentLine("parent::beforeSave()");
            $tableClass->addMethod($beforeSaveMethod);

            $beforeSaveMethod = new AbstractClassMethod("beforeCreate");
            $beforeSaveMethod->addContentLine("parent::beforeCreate()");
            $tableClass->addMethod($beforeSaveMethod);

            $beforeSaveMethod = new AbstractClassMethod("beforeUpdate");
            $beforeSaveMethod->addContentLine("parent::beforeUpdate()");
            $tableClass->addMethod($beforeSaveMethod);

            $afterFetchMethod = new AbstractClassMethod("afterFetch");
            $beforeSaveMethod->addContentLine("parent::afterFetch()");
            $tableClass->addMethod($afterFetchMethod);*/

            $initializeMethod = new AbstractClassMethod("initialize");

            $useEntities = [];

            if (isset($table['ref_many_to_one'])) {

                $table['ref_many_to_one'] = $this->prepareRef($table['ref_many_to_one']);

                foreach ($table['ref_many_to_one'] as $ref) {

                    $aliasModel = $ref['alias'];

                    $initializeMethod->addContentLine("\$this->belongsTo('{$ref['column']}', '{$this->entityNamespace}\\{$ref['model']}', '{$ref['ref_column']}', array('alias' => '{$aliasModel}', 'reusable' => true));");
                    $getMethod = new AbstractClassMethod('get' . $aliasModel);
                    $getMethod->addContentLine("return \$this->getRelated('{$aliasModel}', \$parameters);");
                    if (!in_array($ref['model'], $useEntities, true)) {
                        $tableClass->addUse("\\{$this->entityNamespace}\\{$ref['model']}");
                        $useEntities[] = $ref['model'];
                    }
                    $getMethod->setReturn("{$ref['model']}");
                    $getMethodParam1 = new AbstractMethodParam("parameters");
                    $getMethodParam1->setDefaultValue("null");
                    $getMethod->addParam($getMethodParam1);

                    $tableClass->addMethod($getMethod);
                }
            }

            if (isset($table['ref_one_to_many'])) {

                $table['ref_one_to_many'] = $this->prepareRef($table['ref_one_to_many'], 'ref_column');

                foreach ($table['ref_one_to_many'] as $ref) {

                    $aliasModel = $ref['alias'];

                    $initializeMethod->addContentLine("\$this->hasMany('{$ref['column']}', '{$this->entityNamespace}\\{$ref['model']}', '{$ref['ref_column']}', array('alias' => '{$aliasModel}', 'reusable' => true));");

                    $getMethod = new AbstractClassMethod('get' . SbUtils::getNameMany($aliasModel));
                    $getMethod->addContentLine("return \$this->getRelated('{$aliasModel}', \$parameters);");
                    $getMethodParam1 = new AbstractMethodParam('parameters');
                    $getMethodParam1->setDefaultValue('null');
                    $getMethod->addParam($getMethodParam1);
                    if (!in_array($ref['model'], $useEntities, true)) {
                        $tableClass->addUse("\\{$this->entityNamespace}\\{$ref['model']}");
                        $useEntities[] = $ref['model'];
                    }
                    $getMethod->setReturn("{$ref['model']}[]");
                    $tableClass->addMethod($getMethod);

                    $varNameMany = SbUtils::getNameMany(lcfirst($aliasModel));
                    $addMethod = new AbstractClassMethod('add' . SbUtils::getNameMany($aliasModel));
                    $addMethod->addContentLine("if (!is_array(\${$varNameMany})) {");
                    $addMethod->addContentLine(AbstractClass::tab() . "\${$varNameMany} = array(\${$varNameMany});");
                    $addMethod->addContentLine("}");
                    $addMethod->addContentLine("\$this->{$aliasModel} = \${$varNameMany};");
                    $addMethodParam1 = new AbstractMethodParam($varNameMany);
                    $addMethodParam1->setDefaultValue("array()");
                    $addMethod->addParam($addMethodParam1);
                    $addMethod->setReturn('void');
                    $tableClass->addMethod($addMethod);
                }
            }

            if (isset($table['ref_one_to_one'])) {

                $table['ref_one_to_one'] = $this->prepareRef($table['ref_one_to_one'], 'ref_column');

                foreach ($table['ref_one_to_one'] as $ref) {

                    $aliasModel = $ref['alias'];

                    $initializeMethod->addContentLine("\$this->hasOne('{$ref['column']}', '{$this->entityNamespace}\\{$ref['model']}', '{$ref['ref_column']}', array('alias' => '{$aliasModel}', 'reusable' => true));");

                    $getMethod = new AbstractClassMethod('get' . $aliasModel);
                    $getMethod->addContentLine("return \$this->getRelated('{$aliasModel}', \$parameters);");
                    if (!in_array($ref['model'], $useEntities, true)) {
                        $tableClass->addUse("\\{$this->entityNamespace}\\{$ref['model']}");
                        $useEntities[] = $ref['model'];
                    }
                    $getMethod->setReturn("{$ref['model']}");
                    $getMethodParam1 = new AbstractMethodParam('parameters');
                    $getMethodParam1->setDefaultValue('null');
                    $getMethod->addParam($getMethodParam1);

                    $tableClass->addMethod($getMethod);
                }
            }

            $tableClass->addMethod($initializeMethod);

            $tableClass->generateSettersAndGetters();

            file_put_contents($this->entityDir . "\\Generated\\" . $table['model'] . '.php', $tableClass);

            if (!is_file($this->entityDir . "\\" . $table['model'] . '.php')) {
                file_put_contents($this->entityDir . "\\" . $table['model'] . '.php', $tableClassChild);
            }

            $modelName = $table['model'] . 'Model';
            if (!is_file($this->modelDir . "\\" . $modelName . '.php')) {
                $modelClass = new AbstractClass($modelName);
                $modelClass->setNamespace('Model');
                $modelClass->setExtends('BasicModel');
                $modelClass->addUse('\\'.$this->entityNamespace.'\\' . $table['model']);

                if (isset($table['primary']) && count($table['primary']) == 1) {

                    $getByIdMethodParam = $table['primary'][0];
                    $getByIdMethod = new AbstractClassMethod("get{$table['model']}By" . \Phalcon\Text::camelize($getByIdMethodParam));
                    $getByIdMethod->addContentLine("return {$table['model']}::findFirst([");
                    $getByIdMethod->addContentLine(AbstractClass::tab() . "'{$table['primary'][0]} = ?1',");
                    $getByIdMethod->addContentLine(AbstractClass::tab() . "'bind' => [1 => \$".lcfirst(\Phalcon\Text::camelize($getByIdMethodParam))."]");
                    $getByIdMethod->addContentLine("]);");

                    $getByIdMethod->setReturn("{$table['model']}");
                    $getMethodParam1 = new AbstractMethodParam(lcfirst(\Phalcon\Text::camelize($getByIdMethodParam)));
                    $getByIdMethod->addParam($getMethodParam1);
                    $modelClass->addMethod($getByIdMethod);
                }

                file_put_contents($this->modelDir . "\\" . $modelName. '.php', $modelClass);
            }
        }

        $metaFile = '.phpstorm.meta.php';
        if (is_file($metaFile)) {

            $metaContent = file_get_contents($metaFile);
            $modelsMeta = '\Sb\Phalcon\Model\Repository::getModel(\'\') => [' . "\n";
            $modelsMeta .= '            "PageContent\\\\\\PageContent" instanceof \Model\PageContent\PageContent,' . "\n";

            foreach ($tables as $table) {
                $modelName = $table['model'];
                $modelsMeta .= "            \"{$modelName}\" instanceof \\Model\\{$modelName}Model,\n";
            }

            $modelsMeta .= "\n        ]";

            if (preg_match("#\\\\Sb\\\\Phalcon\\\\Model\\\\Repository::getModel\(''\) => \[(.*?)\]#is", $metaContent, $m)) {
                $metaContent = preg_replace("#\\\\Sb\\\\Phalcon\\\\Model\\\\Repository::getModel\(''\) => \[(.*?)\]#is", $modelsMeta, $metaContent);
            }

            file_put_contents($metaFile, $metaContent);
        }
    }

} 