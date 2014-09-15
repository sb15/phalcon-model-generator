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
        $commonClass = new AbstractClass("Basic");
        $commonClass->setExtends('Model');
        $commonClass->setNamespace($this->entityNamespaceGenerated);
        $commonClass->addUse("\\Sb\\Utils", "SbUtils");
        $commonClass->addUse('\Phalcon\Mvc\Model');

        $filedList = new AbstractClassField("fieldList");
        $filedList->setDefault("array()");

        $populateMethod = new AbstractClassMethod("populate");
        $populateMethodParam1 = new AbstractMethodParam("data");
        $populateMethodParam1->setDefaultValue("array()");
        $populateMethod->addParam($populateMethodParam1);

        $populateMethod->addContentLine('foreach ($data as $k => $v) {');
        $populateMethod->addContentLine(AbstractClass::tab(1) . 'if (in_array($k, $this->fieldList)) {');
        $populateMethod->addContentLine(AbstractClass::tab(2) . '$fn = "set" . SbUtils::wordUnderscoreToCamelCase($k);');
        $populateMethod->addContentLine(AbstractClass::tab(2) . '$this->$fn($v);');
        $populateMethod->addContentLine(AbstractClass::tab(1) . '}');
        $populateMethod->addContentLine('}');

        $commonClass->addField($filedList);
        $commonClass->addMethod($populateMethod);
        file_put_contents($this->entityDir . "\\Generated\\Common.php", $commonClass);


        $basicModel = new AbstractClass('Basic');
        $basicModel->setNamespace('Model');
        $diField = new AbstractClassField('di');
        $diField->setScope('protected');
        $basicModel->addField($diField);
        $setDiMethod = new AbstractClassMethod('setDI');
        $setDiMethodParam = new AbstractMethodParam('di');
        $setDiMethod->addParam($setDiMethodParam);
        $setDiMethod->addContentLine('$this->di = $di;');
        $basicModel->addMethod($setDiMethod);
        $getDiMethod = new AbstractClassMethod('getDI');
        $getDiMethod->addContentLine('return $this->di;');
        $getDiMethod->setReturn('\Phalcon\DI\FactoryDefault');
        $basicModel->addMethod($getDiMethod);

        $getModelsManager = new AbstractClassMethod('getModelsManager');
        $getModelsManager->addContentLine('return $this->getDI()->get(\'modelsManager\');');
        $getModelsManager->setReturn('\Phalcon\Mvc\Model\ManagerInterface');
        $basicModel->addMethod($getModelsManager);

        $getQueryMethod = new AbstractClassMethod('getQuery');
        $getQueryMethodParam = new AbstractMethodParam('phql');
        $getQueryMethod->addParam($getQueryMethodParam);
        $getQueryMethod->addContentLine('$query = new \Phalcon\Mvc\Model\Query($phql);');
        $getQueryMethod->addContentLine('$query->setDI($this->getDI());');
        $getQueryMethod->addContentLine('return $query;');
        $getQueryMethod->setReturn('\Phalcon\Mvc\Model\Query');
        $basicModel->addMethod($getQueryMethod);

        file_put_contents($this->modelDir . "\\Basic.php", $basicModel);

    }

    public function generate()
    {

        $dbScheme = new Scheme($this->di);
        $tables = $dbScheme->getScheme();

        //var_dump($tables);

        if (!is_dir($this->entityDir . "\\" . 'Generated')) {
            mkdir($this->entityDir . "\\" . 'Generated', 0777, true);
        }

        $this->generateCommonMethods();

        $modelsRepositoryClass = new AbstractClass('ModelsRepository');
        $modelsRepositoryClass->setNamespace('Model');
        $modelsField = new AbstractClassField('models');
        $modelsField->setDefault("array()");
        $modelsRepositoryClass->addField($modelsField);
        $diField = new AbstractClassField('di');
        $modelsRepositoryClass->addField($diField);
        $constructMethod = new AbstractClassMethod('__construct');
        $constructMethodParam = new AbstractMethodParam('di');
        $constructMethod->addParam($constructMethodParam);
        $constructMethod->addContentLine('$this->di = $di;');
        $modelsRepositoryClass->addMethod($constructMethod);
        $getDiMethod = new AbstractClassMethod('getDI');
        $getDiMethod->addContentLine('return $this->di;');
        $getDiMethod->setReturn('\Phalcon\DiInterface');
        $modelsRepositoryClass->addMethod($getDiMethod);
        $getModelMethod = new AbstractClassMethod('getModel');
        $getModelMethodParam = new AbstractMethodParam('modelName');
        $getModelMethod->addParam($getModelMethodParam);
        $getModelMethod->addContentLine('if (!array_key_exists($modelName, $this->models)) {');
        $getModelMethod->addContentLine(AbstractClass::tab() . '$namespace = \'\\\\Model\\\\\'.$modelName;');
        $getModelMethod->addContentLine(AbstractClass::tab() . '$newModel = new $namespace;');
        $getModelMethod->addContentLine(AbstractClass::tab() . '$newModel->setDI($this->di);');
        $getModelMethod->addContentLine(AbstractClass::tab() . '$this->models[$modelName] = $newModel;');
        $getModelMethod->addContentLine('}');
        $getModelMethod->addContentLine('return $this->models[$modelName];');
        $getModelMethod->setScope('private');
        $modelsRepositoryClass->addMethod($getModelMethod);


        foreach ($tables as $table) {
            $tableClass = new AbstractClass($table['model']);
            $tableClass->setExtends("Basic");
            $tableClass->setNamespace($this->entityNamespaceGenerated);

            $tableClassChild = new AbstractClass($table['model']);
            $tableClassChild->setExtends("Generated\\" . $table['model']);
            $tableClassChild->setNamespace($this->entityNamespace);

            $fieldList = array();
            foreach ($table['columns'] as $field) {
                $fieldField = new AbstractClassField($field);
                $fieldField->setScope("protected");
                $tableClass->addField($fieldField);

                $fieldList[] = '\'' . SbUtils::wordUnderscoreToCamelCaseFirstLower($field) . '\'';
            }
            $fieldListField = new AbstractClassField("fieldList");
            $fieldListField->setDefault($fieldList);
            $fieldListField->setScope("public");
            $tableClass->addField($fieldListField);

            $getSourceMethod = new AbstractClassMethod("getSource");
            $getSourceMethod->setScope("public");
            $getSourceMethod->addContentLine("return \"{$table['name']}\";");
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

            if (isset($table['ref_many_to_one'])) {
                foreach ($table['ref_many_to_one'] as $ref) {
                    $initializeMethod->addContentLine("\$this->belongsTo(\"{$ref['column']}\", '{$this->entityNamespace}\\{$ref['model']}', \"{$ref['ref_column']}\", array('alias' => '{$ref['model']}'));");
                    $getMethod = new AbstractClassMethod('get' . $ref['model']);
                    $getMethod->addContentLine("return \$this->getRelated('{$ref['model']}', \$parameters);");
                    $getMethod->setReturn("\\{$this->entityNamespace}\\{$ref['model']}");
                    $getMethodParam1 = new AbstractMethodParam("parameters");
                    $getMethodParam1->setDefaultValue("null");
                    $getMethod->addParam($getMethodParam1);

                    $tableClass->addMethod($getMethod);
                }
            }

            if (isset($table['ref_one_to_many'])) {
                foreach ($table['ref_one_to_many'] as $ref) {
                    $initializeMethod->addContentLine("\$this->hasMany(\"{$ref['column']}\", '{$this->entityNamespace}\\{$ref['model']}', \"{$ref['ref_column']}\", array('alias' => '{$ref['model']}'));");

                    $getMethod = new AbstractClassMethod('get' . SbUtils::getNameMany($ref['model']));
                    $getMethod->addContentLine("return \$this->getRelated('{$ref['model']}', \$parameters);");
                    $getMethodParam1 = new AbstractMethodParam('parameters');
                    $getMethodParam1->setDefaultValue('null');
                    $getMethod->addParam($getMethodParam1);
                    $getMethod->setReturn("\\{$this->entityNamespace}\\{$ref['model']}[]");
                    $tableClass->addMethod($getMethod);

                    $varNameMany = SbUtils::getNameMany(lcfirst($ref['model']));
                    $addMethod = new AbstractClassMethod('add' . SbUtils::getNameMany($ref['model']));
                    $addMethod->addContentLine("if (!is_array(\${$varNameMany})) {");
                    $addMethod->addContentLine(AbstractClass::tab() . "\${$varNameMany} = array(\${$varNameMany});");
                    $addMethod->addContentLine("}");
                    $addMethod->addContentLine("\$this->{$ref['model']} = \${$varNameMany};");
                    $addMethodParam1 = new AbstractMethodParam($varNameMany);
                    $addMethodParam1->setDefaultValue("array()");
                    $addMethod->addParam($addMethodParam1);
                    $addMethod->setReturn('void');
                    $tableClass->addMethod($addMethod);
                }
            }

            if (isset($table['ref_one_to_one'])) {
                foreach ($table['ref_one_to_one'] as $ref) {
                    $initializeMethod->addContentLine("\$this->hasOne(\"{$ref['column']}\", '{$this->entityNamespace}\\{$ref['model']}', \"{$ref['ref_column']}\", array('alias' => '{$ref['model']}'));");

                    $getMethod = new AbstractClassMethod('get' . $ref['model']);
                    $getMethod->addContentLine("return \$this->getRelated('{$ref['model']}', \$parameters);");
                    $getMethod->setReturn("\\{$this->entityNamespace}\\{$ref['model']}");
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

            if (!is_file($this->modelDir . "\\" . $table['model'] . '.php')) {
                $modelClass = new AbstractClass($table['model']);
                $modelClass->setNamespace('Model');
                $modelClass->setExtends('Basic');

                file_put_contents($this->modelDir . "\\" . $table['model']. '.php', $modelClass);
            }

            $getModelMethod = new AbstractClassMethod('get' . $table['model']);
            $getModelMethod->addContentLine('return $this->getModel(\''.$table['model'].'\');');
            $getModelMethod->setReturn($table['model']);
            $modelsRepositoryClass->addMethod($getModelMethod);

        }

        file_put_contents($this->modelDir . "\\ModelsRepository.php", $modelsRepositoryClass);

    }

} 