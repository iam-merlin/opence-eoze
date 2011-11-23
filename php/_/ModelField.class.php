<?php

interface ModelField {

	const T_INT = 'int';
	const T_INTEGER = 'int';
	const T_STRING = 'string';
	const T_TEXT = 'text';
	const T_DATE = 'date';
	const T_TIME = 'time';
	const T_DATETIME = 'datetime';
	const T_BOOL = 'bool';
	const T_BOOLEAN = 'bool';
	const T_FLOAT = 'float';
	const T_DECIMAL = 'decimal';
	const T_ENUM = 'enum';

	function getName();

//	function select(ModelTableQuery $query);

//	function orderClause($dir, $tableAlias = null);

	function getType();

	function isNullable();
	
	function castValue($value);
}