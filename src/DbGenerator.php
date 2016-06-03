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
        if (!is_file($this->entityDir . "\\Generated\\Basic.php")) {
            $basicEntityClass = new AbstractClass('Basic');
            $basicEntityClass->addUse('\Phalcon\Mvc\Model');
            $basicEntityClass->setExtends('Model');
            $basicEntityClass->setNamespace($this->entityNamespaceGenerated);

            $cacheField = new AbstractClassField('_cache');
            $cacheField->setScope('protected');
            $cacheField->setStatic();
            $cacheField->setDefault('array()');
            $basicEntityClass->addField($cacheField);

            $disableFirstLevelCacheField = new AbstractClassField('_disableFirstLevelCache');
            $disableFirstLevelCacheField->setScope('protected');
            $disableFirstLevelCacheField->setStatic();
            $disableFirstLevelCacheField->setDefault(false);
            $basicEntityClass->addField($disableFirstLevelCacheField);


            $createKeyMethod = new AbstractClassMethod('_createKey');
            $createKeyMethodParam1 = new AbstractMethodParam('parameters');
            $createKeyMethodParam1->setDefaultValue('null');
            $createKeyMethod->addParam($createKeyMethodParam1);
            $createKeyMethod->setScope('protected');
            $createKeyMethod->setStatic();

            $createKeyMethod->addContentLine('$uniqueKey = array();');
            $createKeyMethod->addContentLine('');
            $createKeyMethod->addContentLine('foreach ($parameters as $key => $value) {');
            $createKeyMethod->addContentLine(AbstractClass::tab(1) . 'if (is_scalar($value)) {');
            $createKeyMethod->addContentLine(AbstractClass::tab(2) . '$uniqueKey[] = $key . \':\' . $value;');
            $createKeyMethod->addContentLine(AbstractClass::tab(1) . '} else {');
            $createKeyMethod->addContentLine(AbstractClass::tab(2) . 'if (is_array($value)) {');
            $createKeyMethod->addContentLine(AbstractClass::tab(3) . '$uniqueKey[] = $key . \':[\' . self::_createKey($value) .\']\';');
            $createKeyMethod->addContentLine(AbstractClass::tab(2) . '}');
            $createKeyMethod->addContentLine(AbstractClass::tab(1) . '}');
            $createKeyMethod->addContentLine('}');
            $createKeyMethod->addContentLine('');
            $createKeyMethod->addContentLine('return implode(\',\', $uniqueKey);');

            $basicEntityClass->addMethod($createKeyMethod);

            $findMethod = new AbstractClassMethod('find');
            $findMethodParam1 = new AbstractMethodParam('parameters');
            $findMethodParam1->setDefaultValue('null');
            $findMethod->addParam($findMethodParam1);
            $findMethod->setScope('public');
            $findMethod->setStatic();

            $findMethod->addContentLine('if (self::$_disableFirstLevelCache) {');
            $findMethod->addContentLine(AbstractClass::tab(1) . 'return parent::find($parameters);');
            $findMethod->addContentLine('}');
            $findMethod->addContentLine('');
            $findMethod->addContentLine('$key = get_called_class() . \':find:\' . self::_createKey($parameters);');
            $findMethod->addContentLine('');
            $findMethod->addContentLine('if (!isset(self::$_cache[$key])) {');
            $findMethod->addContentLine(AbstractClass::tab(1) . 'self::$_cache[$key] = parent::find($parameters);');
            $findMethod->addContentLine('}');
            $findMethod->addContentLine('');
            $findMethod->addContentLine('return self::$_cache[$key];');

            $basicEntityClass->addMethod($findMethod);

            $findFirstMethod = new AbstractClassMethod('findFirst');
            $findFirstMethodParam1 = new AbstractMethodParam('parameters');
            $findFirstMethodParam1->setDefaultValue('null');
            $findFirstMethod->addParam($findFirstMethodParam1);
            $findFirstMethod->setScope('public');
            $findFirstMethod->setStatic();

            $findFirstMethod->addContentLine('if (self::$_disableFirstLevelCache) {');
            $findFirstMethod->addContentLine(AbstractClass::tab(1) . 'return parent::findFirst($parameters);');
            $findFirstMethod->addContentLine('}');
            $findFirstMethod->addContentLine('');
            $findFirstMethod->addContentLine('$key = get_called_class() . \':findFirst:\' . self::_createKey($parameters);');
            $findFirstMethod->addContentLine('');
            $findFirstMethod->addContentLine('if (!isset(self::$_cache[$key])) {');
            $findFirstMethod->addContentLine(AbstractClass::tab(1) . 'self::$_cache[$key] = parent::findFirst($parameters);');
            $findFirstMethod->addContentLine('}');
            $findFirstMethod->addContentLine('');
            $findFirstMethod->addContentLine('return self::$_cache[$key];');

            $basicEntityClass->addMethod($findFirstMethod);

            $disableFirstLevelCacheMethod = new AbstractClassMethod('disableFirstLevelCache');
            $disableFirstLevelCacheMethod->setScope('public');
            $disableFirstLevelCacheMethod->setStatic();

            $disableFirstLevelCacheMethod->addContentLine('self::$_disableFirstLevelCache = true;');
            $basicEntityClass->addMethod($disableFirstLevelCacheMethod);

            file_put_contents($this->entityDir . "\\Generated\\Basic.php", $basicEntityClass);
        }

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

    public function prepareRefMany($ref)
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

                $ref[$k]['alias'] = SbUtils::getNameMany($refData['model']) . 'Via' . $refData['intermediate_model'];

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
            $tableClass->setExtends('Basic');
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
            $initializeMethod->addContentLine('$this->useDynamicUpdate(true);');

            if (isset($table['ref_many_to_one'])) {

                $table['ref_many_to_one'] = $this->prepareRef($table['ref_many_to_one']);

                foreach ($table['ref_many_to_one'] as $ref) {

                    $aliasModel = $ref['alias'];

                    $initializeMethod->addContentLine("\$this->belongsTo('{$ref['column']}', '{$this->entityNamespace}\\{$ref['model']}', '{$ref['ref_column']}', array('alias' => '{$aliasModel}', 'reusable' => true));");

                    $getMethod = new AbstractClassMethod('get' . $aliasModel);
                    $getMethod->addContentLine("return \$this->getRelated('{$aliasModel}', \$parameters);");
                    $getMethod->setReturn("\\{$this->entityNamespace}\\{$ref['model']}");
                    $getMethodParam1 = new AbstractMethodParam("parameters");
                    $getMethodParam1->setDefaultValue("null");
                    $getMethod->addParam($getMethodParam1);

                    $variableName = lcfirst($aliasModel);
                    $setMethod = new AbstractClassMethod('set' . $aliasModel);
                    $setMethodParam1 = new AbstractMethodParam($variableName);
                    $setMethodParam1->setDocType("\\{$this->entityNamespace}\\{$ref['model']}|null");
                    $setMethod->addParam($setMethodParam1);
                    $setMethod->addContentLine("\$this->{$ref['column']} = \${$variableName} ? \${$variableName}->getId() : null;");
                    $setMethod->addContentLine("return \$this;");
                    $setMethod->setReturn('$this');

                    $tableClass->addMethod($getMethod);
                    $tableClass->addMethod($setMethod);
                }
            }

            if (isset($table['ref_one_to_many'])) {

                $table['ref_one_to_many'] = $this->prepareRef($table['ref_one_to_many'], 'ref_column');

                foreach ($table['ref_one_to_many'] as $ref) {

                    $aliasModel = $ref['alias'];

                    $initializeMethod->addContentLine("\$this->hasMany('{$ref['column']}', '{$this->entityNamespace}\\{$ref['model']}', '{$ref['ref_column']}', array('alias' => '{$aliasModel}', 'reusable' => true));");

					$variableName = lcfirst($aliasModel);
					$varNameMany = SbUtils::getNameMany(lcfirst($aliasModel));

                	$getMethod = new AbstractClassMethod('get' . SbUtils::getNameMany($aliasModel));
                    $getMethod->addContentLine("return \$this->getRelated('{$aliasModel}', \$parameters);");
                    $getMethodParam1 = new AbstractMethodParam('parameters');
                    $getMethodParam1->setDefaultValue('null');
                    $getMethod->addParam($getMethodParam1);
                    $getMethod->setReturn("\\{$this->entityNamespace}\\{$ref['model']}[]");
                    $tableClass->addMethod($getMethod);

                	$addMethod = new AbstractClassMethod('add' . $aliasModel);
                    $addMethod->addContentLine("\$this->_related['{$aliasModel}'][] = \${$variableName};");
                    $addMethod->addContentLine("return \$this;");
                    $addMethodParam1 = new AbstractMethodParam($variableName);                    
                    $addMethodParam1->setType("\\{$this->entityNamespace}\\{$ref['model']}");
                    $addMethod->addParam($addMethodParam1);
                    $addMethod->setReturn('$this');
                    $tableClass->addMethod($addMethod);
                }
            }

            if (isset($table['ref_one_to_one'])) {

                $table['ref_one_to_one'] = $this->prepareRef($table['ref_one_to_one'], 'ref_column');

                foreach ($table['ref_one_to_one'] as $ref) {

                    $aliasModel = $ref['alias'];
                    $variableName = lcfirst($aliasModel);

                    $initializeMethod->addContentLine("\$this->hasOne('{$ref['column']}', '{$this->entityNamespace}\\{$ref['model']}', '{$ref['ref_column']}', array('alias' => '{$aliasModel}', 'reusable' => true));");

                    $getMethod = new AbstractClassMethod('get' . $aliasModel);

                    $getMethod->addContentLine("\${$variableName} = \$this->getRelated('{$aliasModel}', \$parameters);");
                    $getMethod->addContentLine("if (false === \${$variableName}) {");
                    $getMethod->addContentLine(AbstractClass::tab() . "\${$variableName} = new \\{$this->entityNamespace}\\{$ref['model']}();");
                    $getMethod->addContentLine(AbstractClass::tab() . "\${$variableName}->set" . \Phalcon\Text::camelize($ref['ref_column']) . "(\$this->getId());");
                    $getMethod->addContentLine('}');
                    $getMethod->addContentLine("return \${$variableName};");

                    $getMethod->setReturn("\\{$this->entityNamespace}\\{$ref['model']}");
                    $getMethodParam1 = new AbstractMethodParam('parameters');
                    $getMethodParam1->setDefaultValue('null');
                    $getMethod->addParam($getMethodParam1);

                    $tableClass->addMethod($getMethod);
                }
            }

            if (isset($table['ref_many_to_many'])) {

                $table['ref_many_to_many'] = $this->prepareRefMany($table['ref_many_to_many']);

                foreach ($table['ref_many_to_many'] as $ref) {
                    $aliasModel = $ref['alias'];
                    $variableName = lcfirst($aliasModel);
                    $varNameMany = SbUtils::getNameMany(lcfirst($aliasModel));
                    $intermediateModel = $ref['intermediate_model'];

                    $initializeMethod->addContentLine("\$this->hasManyToMany('{$ref['intermediate_column']}', ".
                        "'{$this->entityNamespace}\\{$ref['intermediate_model']}', '{$ref['intermediate_ref_column']}', ".
                        "'{$ref['column']}', '{$this->entityNamespace}\\{$ref['model']}', '{$ref['ref_column']}', ".
                        "array('alias' => '{$aliasModel}', 'reusable' => true));");

                	$getMethod = new AbstractClassMethod('get' . SbUtils::getNameMany($aliasModel));
                    $getMethod->addContentLine("return \$this->getRelated('{$aliasModel}', \$parameters);");
                    $getMethod->setReturn("\\{$this->entityNamespace}\\{$ref['model']}[]|null");
                    $getMethodParam1 = new AbstractMethodParam("parameters");
                    $getMethodParam1->setDefaultValue("null");
                    $getMethod->addParam($getMethodParam1);
                    $tableClass->addMethod($getMethod);

					$addMethod = new AbstractClassMethod('add' . $aliasModel);
                    $addMethod->addContentLine("\$this->_related['{$aliasModel}'][] = \${$variableName};");
                    $addMethod->addContentLine("return \$this;");
                    $addMethodParam1 = new AbstractMethodParam($variableName);                    
                    $addMethodParam1->setType("\\{$this->entityNamespace}\\{$ref['model']}");
                    $addMethod->addParam($addMethodParam1);
                    $addMethod->setReturn('$this');
                    $tableClass->addMethod($addMethod);

                	$deleteMethod = new AbstractClassMethod('delete' . $aliasModel);					
                    $deleteMethod->addContentLine("\$this->{$intermediateModel}->delete(function(\$object) use (\${$variableName}) {");
                    $deleteMethod->addContentLine(AbstractClass::tab() . "/** @var \\{$this->entityNamespace}\\{$intermediateModel} \$object */");
                    $deleteMethod->addContentLine(AbstractClass::tab() . "return \$object->get{$ref['model']}Id() === \${$variableName}->getId();");
                    $deleteMethod->addContentLine('});');
                    $deleteMethod->addContentLine("return \$this;");
					$deleteMethodParam1 = new AbstractMethodParam($variableName);
                    $deleteMethodParam1->setType("\\{$this->entityNamespace}\\{$ref['model']}");
                    $deleteMethod->addParam($deleteMethodParam1);
                    $deleteMethod->setReturn('$this');
                    $tableClass->addMethod($deleteMethod);
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
            $modelsMeta .= '            "PageContent\\\\\\PageContent" instanceof \Model\PageContent\PageContentModel,' . "\n";

            foreach ($tables as $table) {
                $modelName = $table['model'];
                $modelsMeta .= "            \"{$modelName}\" instanceof \\Model\\{$modelName}Model,\n";
            }

            $modelsMeta = rtrim($modelsMeta, "\n");

            $modelsMeta .= "\n        ]";

            if (preg_match("#\\\\Sb\\\\Phalcon\\\\Model\\\\Repository::getModel\(''\) => \[(.*?)\]#is", $metaContent, $m)) {
                $metaContent = preg_replace("#\\\\Sb\\\\Phalcon\\\\Model\\\\Repository::getModel\(''\) => \[(.*?)\]#is", $modelsMeta, $metaContent);
            }

            file_put_contents($metaFile, $metaContent);
        }
    }

} 