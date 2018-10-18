<?php
namespace Orm;

use Bitrix\Main\Loader;

abstract class Base extends \Bitrix\Main\Entity\DataManager
{
    public static function getMap()
    {
        $fieldsResult = [];

        if(Loader::includeModule('perfmon'))
        {
            $obTable = new \CPerfomanceTable;
            $obTable->Init(static::getTableName());
            $arFields = $obTable->GetTableFields(false, true);
            $arUniqueIndexes = $obTable->GetUniqueIndexes();
            $hasID = false;
            foreach ($arUniqueIndexes as $indexName => $indexColumns)
            {
                if(array_values($indexColumns) === array("ID"))
                    $hasID = $indexName;
            }
            if ($hasID)
            {
                $arUniqueIndexes = array($hasID => $arUniqueIndexes[$hasID]);
            }
            $obSchema = new \CPerfomanceSchema;
            $arParents = $obSchema->GetParents(static::getTableName());

            foreach ($arFields as $columnName => $columnInfo)
            {
                if ($columnInfo["orm_type"] === "boolean")
                {
                    $columnInfo["nullable"] = true;
                    $columnInfo["type"] = "bool";
                    $columnInfo["length"] = "";
                    $columnInfo["enum_values"] = array('N', 'Y');
                }

                if (
                    $columnInfo["type"] === "int"
                    && ($columnInfo["default"] > 0)
                    && !$columnInfo["nullable"]
                )
                {
                    $columnInfo["nullable"] = true;
                }

                $match = array();
                if (
                    preg_match("/^(.+)_TYPE\$/", $columnName, $match)
                    && array_key_exists($match[1], $arFields)
                    && $columnInfo["length"] == 4
                )
                {
                    $columnInfo["nullable"] = true;
                    $columnInfo["orm_type"] = "enum";
                    $columnInfo["enum_values"] = array('text', 'html');
                }

                $default = $columnInfo["default"];
                if (!is_numeric($default) && $default != "")
                    $default = "'".$default."'";

                $fieldsResult[$columnName]['data_type'] = $columnInfo["orm_type"];

                foreach ($arUniqueIndexes as $indexName => $arColumns)
                {
                    if (in_array($columnName, $arColumns))
                    {
                        $fieldsResult[$columnName]['primary'] = true;
                        break;
                    }
                }

                if ($columnInfo["increment"])
                {
                    $fieldsResult[$columnName]['autocomplete'] = true;
                }

                if (!$fieldsResult[$columnName]['primary'] && $columnInfo["nullable"] === false)
                {
                    $fieldsResult[$columnName]['required'] = true;
                }

                if ($columnInfo["orm_type"] === "boolean" || $columnInfo["orm_type"] === "enum")
                {
                    $fieldsResult[$columnName]['values'] = $columnInfo["enum_values"];
                }
            }

            foreach ($arParents as $columnName => $parentInfo)
            {
                $parentTableParts = explode("_", $parentInfo["PARENT_TABLE"]);
                array_shift($parentTableParts);
                $parentModuleNamespace = ucfirst($parentTableParts[0]);
                $parentClassName = \Bitrix\Main\Entity\Base::snake2camel(implode("_", $parentTableParts));

                $columnNameEx = preg_replace("/_ID\$/", "", $columnName);

                $fieldsResult[$columnNameEx] = [
                    'data_type' => 'Bitrix\\'.$parentModuleNamespace.'\\'.$parentClassName,
                    'reference' => [
                        '=this'.$columnName => 'ref.'.$parentInfo["PARENT_COLUMN"]
                    ]
                ];
            }

        }

        return $fieldsResult;
    }
}