<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/

/** @defgroup ServicesDatabase Services/Database
 */

//pear MDB2 abstraction layer
include_once ("MDB2.php");

define("DB_FETCHMODE_ASSOC", MDB2_FETCHMODE_ASSOC);
define("DB_FETCHMODE_OBJECT", MDB2_FETCHMODE_OBJECT);

//echo "-".DB_FETCHMODE_ASSOC."-";
//echo "+".DB_FETCHMODE_OBJECT."+";


/**
* Database Wrapper
*
* this class should extend PEAR::DB, add error Management
* in case of a db-error in any database query the ilDB-class raises an error
*
* @author Peter Gabriel <peter@gabriel-online.net>
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
* @ingroup ServicesDatabase
*/
class ilDB
{
	/**
	* error class
	* @var object error_class
	* @access private
	*/
	var $error_class;

	/**
	* database handle from pear database class.
	* @var string
	*/
	var $db;

	/**
	* database-result-object
	* @var string
	*/
	var $result;

	/**
	* myqsl max_allowed_packet size
	* @var int
	*/
	var $max_allowed_packet_size;


	/**
	* constructor
	*
	* set up database conncetion and the errorhandling
	*
	* @param string dsn database-connection-string for pear-db
	*/
	function ilDB($dsn)
	{
		//call parent constructor
//		$parent = get_parent_class($this);
//		$this->$parent();

		//set up error handling
		$this->error_class = new ilErrorHandling();
//		$this->setErrorHandling(PEAR_ERROR_CALLBACK, array($this->error_class,'errorHandler'));

		//check dsn
		if ($dsn=="")
			$this->raiseError("No DSN given");

		$this->dsn = $dsn;

		//connect to database
		//$this->db = DB::connect($this->dsn, true);
		$this->db = MDB2::connect($this->dsn, array("use_transactions" => true));
		
		$this->loadMDB2Extensions();
		
		// set empty value portability to PEAR::DB behaviour
		if (!$this->isDbError($this->db))
		{
			$cur = ($this->db->getOption("portability") & MDB2_PORTABILITY_EMPTY_TO_NULL);
			$this->db->setOption("portability", $this->db->getOption("portability") - $cur);

			$cur = ($this->db->getOption("portability") & MDB2_PORTABILITY_FIX_CASE);
			$this->db->setOption("portability", $this->db->getOption("portability") - $cur);
		}

		//check error
		$this->handleError($this->db);
		
		// SET 'max_allowed_packet' (only possible for mysql version 4)
		$this->setMaxAllowedPacket();
		
		// NOTE: Two sourcecodes use this or a similar handling:
		// - classes/class.ilDB.php
		// - setup/classes/class.ilClient.php
		if ($this->isMysql4_1OrHigher())
		{
			$this->query("SET NAMES utf8");
			$this->query("SET SESSION SQL_MODE = ''");
		}

		return true;
	} //end constructor

	
	//
	// General and MDB2 related functions
	//

	/**
	* Handle MDB2 Errors
	*
	* @param	mixed 	result set or anything that is a MDB2::error if
	*					something went wrong
	*/
	function handleError($a_res, $a_info = "", $a_level = "")
	{
		if (MDB2::isError($a_res))
		{
			if ($a_level == "")
			{
				$a_level = $this->error_class->FATAL;
			}

			$this->raiseError("ilDB Error: ".$a_info."<br />".
				$a_res->getMessage()."<br />".$a_res->getUserInfo(), $a_level);
		}
		
		return $a_res;
	}
	
	/**
	* Raise an error
	*/
	function raiseError($a_message, $a_level = "")
	{
		if ($a_level == "")
		{
			$a_level = $this->error_class->FATAL;
		}
		$this->error_class->raiseError($a_message, $a_level);
	}
	
	/**
	 * load additional mdb2 extensions and set their constants 
	 *
	 * @access protected
	 */
	protected function loadMDB2Extensions()
	{
		if (!$this->isDbError($this->db))
		{
			$this->db->loadModule('Extended');
			define('DB_AUTOQUERY_SELECT',MDB2_AUTOQUERY_SELECT);
			define('DB_AUTOQUERY_INSERT',MDB2_AUTOQUERY_INSERT);
			define('DB_AUTOQUERY_UPDATE',MDB2_AUTOQUERY_UPDATE);
			define('DB_AUTOQUERY_DELETE',MDB2_AUTOQUERY_DELETE);
		}
	}

	/**
	* Check error
	*/
	static function isDbError($a_res)
	{
		return MDB2::isError($a_res);
	}

	//
	// Data Definition Methods
	//
	
	/**
	* Create a new table in the database
	*/
	function createTable($a_name, $a_definition_array, $a_options = "")
	{
		if ($a_options == "")
		{
			$a_options = array();
		}
		
		$manager = $this->db->loadModule('Manager');
		$r = $manager->createTable($a_name, $a_definition_array, $a_options);

		return $this->handleError($r, "createTable(".$a_name.")");
	}
	
	/**
	* Drop a table
	*/
	function dropTable($a_name)
	{
		$manager = $this->db->loadModule('Manager');
		$r = $manager->dropTable($a_name);

		return $this->handleError($r, "dropTable(".$a_name.")");
	}
	
	/**
	* Alter a table in the database
	*/
	function alterTable($a_name, $a_changes)
	{
		if ($a_options == "")
		{
			$a_options = array();
		}
		
		$manager = $this->db->loadModule('Manager');
		$r = $manager->alterTable($a_name, $a_changes, false);

		return $this->handleError($r, "alterTable(".$a_name.")");
	}

	/**
	* Modify a column within a table.
	* Use this only on aleady "abstracted" tables.
	*
	* @param	string		table name
	* @param	string		column name
	* @param	array		attributes to be changed, e.g. array("length" => 10, "default" => "t")
	*/
	function modifyTableColumn($a_table, $a_column, $a_attributes)
	{
		$manager = $this->db->loadModule('Manager');
		$reverse = $this->db->loadModule('Reverse');
		$def = $reverse->getTableFieldDefinition($a_table, $a_column);
		
		$this->handleError($def, "modifyTableColumn(".$a_table.")");
		
		$def = $def[0];
		unset($def["nativetype"]);
		foreach ($a_attributes as $a => $v)
		{
			$def[$a] = $v;
		}
		
		$a_attributes["definition"] = $def;
		
		$changes = array(
			"change" => array(
				$a_column => $a_attributes
				)
			);

		$r = $manager->alterTable($a_table, $changes, false);

		return $this->handleError($r, "modifyTableColumn(".$a_table.")");
	}

	/**
	* Add a primary key to a table
	*
	* @param	string		table name
	* @param	array		fields for primary key
	* @param	string		key name
	*/
	function addPrimaryKey($a_table, $a_fields, $a_name = "PRIMARY")
	{
		$manager = $this->db->loadModule('Manager');
		
		$fields = array();
		foreach ($a_fields as $f)
		{
			$fields[$f] = array();
		}
		$definition = array (
			'primary' => true,
			'fields' => $fields
		);
		$r = $manager->createConstraint($a_table, $a_name, $definition);

		return $this->handleError($r, "addPrimaryKey(".$a_table.")");
	}
	
	/**
	* Drop a primary key from a table
	*
	* @param	string		table name
	* @param	string		key name
	*/
	function dropPrimaryKey($a_table, $a_name = "PRIMARY")
	{
		$manager = $this->db->loadModule('Manager');
		
		$r = $manager->dropConstraint($a_table, $a_name, true);

		return $this->handleError($r, "dropPrimaryKey(".$a_table.")");
	}

	/**
	* Add an index to a table
	*
	* @param	string		table name
	* @param	array		fields for index
	* @param	string		index name
	*/
	function addIndex($a_table, $a_fields, $a_name = "indx")
	{
		$manager = $this->db->loadModule('Manager');
		
		$fields = array();
		foreach ($a_fields as $f)
		{
			$fields[$f] = array();
		}
		$definition = array (
			'fields' => $fields
		);
		$r = $manager->createIndex($a_table, $a_name, $definition);

		return $this->handleError($r, "addIndex(".$a_table.")");
	}

	/**
	* Drop an index from a table.
	* Note: The index must have been created using MDB2
	*
	* @param	string		table name
	* @param	string		index name
	*/
	function dropIndex($a_table, $a_name = "indx")
	{
		$manager = $this->db->loadModule('Manager');
		
		$r = $manager->dropIndex($a_table, $a_name);

		return $this->handleError($r, "dropIndex(".$a_table.")");
	}

	/**
	* Create a sequence for a table
	*/
	function createSequence($a_table_name, $a_start = 1)
	{
		$manager = $this->db->loadModule('Manager');
		
		$r = $manager->createSequence($a_table_name, $a_start);

		return $this->handleError($r, "createSequence(".$a_table_name.")");
	}
	
	
	/**
	* Drop a sequence for a table
	*/
	function dropSequence($a_table_name)
	{
		$manager = $this->db->loadModule('Manager');
		
		$r = $manager->dropSequence($a_table_name);

		return $this->handleError($r, "dropSequence(".$a_table_name.")");
	}

	
	//
	// Data query and manupilation functions
	//
	
	/**
	* Simple query. This function should only be used for simple select queries
	* without parameters. Data manipulation should not be done with it.
	*
	* Example:
	* - "SELECT * FROM data"
	*
	* For simple data manipulation use manipulate().
	* For complex queries/manipulations use prepare()/prepareManip() and execute.
	*
	* @param string
	* @return object DB
	*/
	function query($sql)
	{
		$r = $this->db->query($sql);

		//$this->logStatement($sql);

		return $this->handleError($r, "query(".$sql.")");
	}

	/**
	* Helper function, should usually not be called
	*/
	function logStatement($a_sql)
	{
		$pos1 = strpos(strtolower($sql), "from ");
		$table = "";
		if ($pos1 > 0)
		{
			$tablef = substr($sql, $pos1+5);
			$pos2 = strpos(strtolower($tablef), " ");
			if ($pos2 > 0)
			{
				$table =substr($tablef, 0, $pos2);
			}
			else
			{
				$table = $tablef;
			}
		}
		if (trim($table) != "")
		{
			if (!is_array($this->ttt) || !in_array($table, $this->ttt))
			{
				echo "<br>".$table;
				$this->ttt[] = $table;
			}
		}
		else
		{
			echo "<br><b>".$sql."</b>";
		}
	}
	
	/**
	* Set limit and offset for a query
	*/
	function setLimit($a_limit, $a_offset = 0)
	{
		$this->db->setLimit($a_limit, $a_offset);
	}
	
	/**
	* Get next ID for an index
	*/
	function nextId($a_table_name)
	{
		// we do not create missing sequences automatically here
		// otherwise misspelled statements result in additional tables
		// please create sequences explicitly in the db update script
		$r = $this->db->nextId($a_table_name, false);

		return $this->handleError($r, "nextId(".$a_table_name.")");
	}

	/**
	* Simple data manipulatoin. This function should only be used for simple data
	* manipulations without parameters. Queries should not be done with it.
	*
	* Example:
	* - "DELETE * FROM data"
	*
	* For simple data queries use query().
	* For complex queries/manipulations use prepare()/prepareManip() and execute.
	*
	* @param	string		DML string
	* @return	int			affected rows
	*/
	function manipulate($sql)
	{
		$r = $this->db->exec($sql);

		return $this->handleError($r, "manipulate(".$sql.")");
	}

	/**
	* Prepare a query (SELECT) statement to be used with execute.
	*
	* @param	String	Query String
	* @param	Array	Placeholder Types
	*
	* @return	Resource handle for the prepared query on success, a MDB2 error on failure.
	*/
	function prepare($a_query, $a_types = null, $a_result_types = null)
	{		
		$res = $this->db->prepare($a_query, $a_types, $a_result_types);
		
		return $this->handleError($res, "prepare(".$a_query.")");
	}

	/**
	* Prepare a data manipulation statement to be used with execute.
	*
	* @param	String	Query String
	* @param	Array	Placeholder Types
	*
	* @return	Resource handle for the prepared query on success, a MDB2 error on failure.
	*/
	function prepareManip($a_query, $a_types = null)
	{
		$res = $this->db->prepare($a_query, $a_types, MDB2_PREPARE_MANIP);
		
		return $this->handleError($res, "prepareManip(".$a_query.")");
	}

	/**
	* Execute a query statement prepared by either prepare() or prepareManip()
	*
	* @param	object		Resource handle of the prepared query.
	* @param	array		Array of data (to be used for placeholders)
	*
	* @return	mixed		A result handle or MDB2_OK on success, a MDB2 error on failure
	*/
	function execute($a_stmt, $a_data = null)
	{
		$res = $a_stmt->execute($a_data);

		return $this->handleError($res, "execute(".$a_stmt->query.")");
	}

	/**
	* Execute a query statement prepared by either prepare() or prepareManip()
	* with multiple data arrays.
	*
	* @param	object		Resource handle of the prepared query.
	* @param	array		Array of array of data (to be used for placeholders)
	*
	* @return	mixed		A result handle or MDB2_OK on success, a MDB2 error on failure
	*/
	function executeMultiple($a_stmt, $a_data)
	{
		$res = $this->db->extended->executeMultiple($a_stmt,$a_data);

		return $this->handleError($res, "executeMultiple(".$a_stmt->query.")");
	}
	
	/**
	* Fetch row as associative array from result set
	*
	* @param	object	result set
	*/
	function fetchAssoc($a_set)
	{
		return $a_set->fetchRow(DB_FETCHMODE_ASSOC);
	}
	
	/**
	* Free a result set
	*/
	function free($a_set)
	{
		return $a_set->free();
	}

	/**
	* Fetch row as object from result set
	*
	* @param	object	result set
	*/
	function fetchObject($a_set)
	{
		return $a_set->fetchObject(DB_FETCHMODE_OBJECT);
	}

	//
	// function and clauses abstraction
	//
	
	/**
	* Get abstract in-clause for given array.
	* Returns an array "field_name IN (?,?,?,...)" depending on the size of the array
	*
	* Example:
	*	$ids = array(10,12,18);
	*	$st = $ilDB->prepare("SELECT * FROM table ".
	*		"WHERE ".$ilDB->in("id", $ids),
	*		$ilDB->addTypesToArray($types, "integer", count($ids)));
	*	$set = $ilDB->execute($st, $ids);
	*/
	function in($a_field, $a_values)
	{
		if (count($a_values) == 0)
		{
			return " 1=2 ";		// return a false statement on empty array
		}
		$str = $a_field." IN (?".str_repeat(",?", count($a_values) - 1).")";
		
		return $str;
	}
	
	/**
	* Adds a type x times to an array
	*/
	function addTypesToArray($a_arr, $a_type, $a_cnt)
	{
		if (!is_array($a_arr))
		{
			$a_arr = array();
		}
		if ($a_cnt > 0)
		{
			$type_arr = array_fill(0, $a_cnt, $a_type);
		}
		else
		{
			$type_arr = array();
		}
		return array_merge($a_arr, $type_arr);
	}
	
	/**
	* now()
	*
	* @todo: oracle version
	*/
	function now()
	{
		return "now()";
	}
	

	//
	// Schema related functions
	//
	
	/**
	* Check, whether a given table exists
	*
	* @param	string		table name
	* @return	boolean		true, if table exists
	*/
	function tableExists($a_table)
	{
		$tables = $this->listTables();
		if (is_array($tables))
		{
			if (in_array($a_table, $tables))
			{
				return true;
			}
		}
		return false;
	}
	
	/**
	* Checks for the existence of a table column
	*
	* @param string $a_table The table name which should be examined
	* @param string $a_column_name The name of the column
	* @return boolean TRUE if the table column exists, FALSE otherwise
	*/
	function tableColumnExists($a_table, $a_column_name)
	{
		
		$column_visibility = false;
		$manager = $this->db->loadModule('Manager');
		$r = $manager->listTableFields($a_table);

		if (!MDB2::isError($r))
		{
			foreach($r as $field)
			{
				if ($field == $a_column_name)
				{
					$column_visibility = true;
				}
			}
		}

		return $column_visibility;
	}
	
	/**
	* Get all tables
	*
	* @return array		Array of table names
	*/
	function listTables()
	{
		$manager = $this->db->loadModule('Manager');
		$r = $manager->listTables();

		if (!MDB2::isError($r))
		{
			return $r;
		}
		
		return false;
	}

	
	//
	// Quote Functions
	//
	
	/**
	* Wrapper for quote method. Deprecated, use prepare/prepareManip instead.
	*/
	function quote($a_query, $a_type = null)
	{
		if ($a_query == "")
		{
			$a_query = "";
		}

		return $this->db->quote($a_query, $a_type);
	}
	
	/**
	* Quote table and field names.
	*
	* Note: IF POSSIBLE, THIS METHOD SHOULD NOT BE USED. Rename
	* your table or field, if it conflicts with a reserved word.
	*
	*/
	function quoteIdentifier($a_identifier)
	{
		return $this->db->quoteIdentifier($a_identifier);
	}

	//
	// Transaction and Locking methods
	//
	
	/**
	* Begin Transaction. Please note that we currently do not use savepoints.
	*
	* @return	boolean		MDB2_OK on success
	*/
	function beginTransaction()
	{
		if (!$this->db->supports('transactions'))
		{
			$this->raiseError("ilDB::beginTransaction: Transactions are not supported.", $this->error_class->FATAL);
		}
		$res = $this->db->beginTransaction();
		
		return $this->handleError($res, "beginTransaction()");
	}
	
	/**
	* Commit a transaction
	*/
	function commit()
	{
		$res = $this->db->commit();
		
		return $this->handleError($res, "commit()");
	}

	/**
	* Rollback a transaction
	*/
	function rollback()
	{
		$res = $this->db->rollback();
		
		return $this->handleError($res, "rollback()");
	}

	/**
	* Lock existing table
	* @param array (tablename => lock type READ, WRITE, READ LOCAL or LOW_PRIORITY) e.g array('tree' => 'WRITE')
	* @return boolean
	*/
	static function _lockTables($a_table_params)
	{
		global $ilDB;
		
		$lock_str = 'LOCK TABLES ';
		$counter = 0;
		foreach($a_table_params as $table_name => $type)
		{
			$lock_str .= $counter++ ? ',' : '';
			$lock_str .= $table_name.' '.$type;
		}

		$ilDB->query($lock_str);

		return true;
	}
	
	/**
	* unlock tables
	*/
	static function _unlockTables()
	{
		global $ilDB;
		
		$ilDB->query('UNLOCK TABLES');

		return true;
	}
	
	
	//
	// MySQL specific, should go to another class
	//
	
	/**
	* get mysql version
	*/
	function getMySQLVersion()
	{
		return mysql_get_server_info();
	}
	

	/**
	* check wether current MySQL server is version 4.0.x or higher
	*/
	function isMysql4_0OrHigher()
	{
		$version = explode(".", $this->getMysqlVersion());
		if((int) $version[0] >= 4)
		{
			return true;
		}
		return false;
	}

	/**
	* check wether current MySQL server is version 4.1.x
	*/
	function isMysql4_1()
	{
		$version = explode(".", $this->getMysqlVersion());
		if ($version[0] == "4" && $version[1] == "1")
		{
			return true;
		}
		
		return false;
	}

	/**
	* check wether current MySQL server is version 4.1.x or higher
	*
	* NOTE: Two sourcecodes use this or a similar handling:
	* - classes/class.ilDB.php
	* - setup/classes/class.ilClient.php
	*/
	function isMysql4_1OrHigher()
	{
		$version = explode(".", $this->getMysqlVersion());
		if ((int)$version[0] >= 5 ||
			((int)$version[0] == 4 && (int)$version[1] >= 1))
		{
			return true;
		}
		
		return false;
	}

	/**
	* Check query size
	*/
	function checkQuerySize($a_query)
	{
		global $lang;

		if(strlen($a_query) >= $this->max_allowed_packet_size)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Set maximum allowed packet size
	*
	* todo@: This is MySQL specific and should go to a MySQL specific class.
	*/
	private function setMaxAllowedPacket()
	{
		// GET MYSQL VERSION
		$query = "SHOW VARIABLES LIKE 'version'";
		$res = $this->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$version = $row->Value;
		}

		// CHANG VALUE IF MYSQL VERSION > 4.0
		if(substr($version,0,1) == "4")
		{
			ini_get("post_max_size");
			$query = "SET GLOBAL max_allowed_packet = ".(int) ini_get("post_max_size") * 1024 * 1024;
			$this->query($query);
		}
		// STORE NEW max_size in member variable
		$query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
		$res = $this->db->query($query);

		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$this->max_allowed_packet_size = $row->Value;
		}

		return true;
	}

//
//
// Older functions. Must be checked.
//
//

	/**
	* Wrapper for Pear autoExecute
	* @param string tablename
	* @param array fields values
	* @param int MDB2_AUTOQUERY_INSERT or MDB2_AUTOQUERY_UPDATE
	* @param string where condition (e.g. "obj_id = '7' AND ref_id = '5'")
	* @return mixed a new DB_result/DB_OK  or a DB_Error, if fail
	*/
	function autoExecute($a_tablename,$a_fields,$a_mode = MDB2_AUTOQUERY_INSERT,$a_where = false)
	{
		$res = $this->db->autoExecute($a_tablename,$a_fields,$a_mode,$a_where);

		return $this->handleError($res, "autoExecute(".$a_tablename.")");
	}

//
//
// Deprecated functions.
//
//

	/**
	* Get last insert id
	*/
	function getLastInsertId()
	{
		$res = $this->db->lastInsertId();
		if(MDB2::isError($res))
		{
			return false;
		}
		return $res;
	}

	/**
	* getOne. DEPRECATED. Should not be used anymore.
	* 
	* this is the wrapper itself. Runs a query and returns the first column of the first row
	* or in case of an error, jump to errorpage
	* 
	* @param string
	* @return object DB
	*/
	function getOne($sql)
	{
		//$r = $this->db->getOne($sql);
		$set = $this->db->query($sql);
		
		$this->handleError($set, "getOne(".$sql.")");
		
		if (!MDB2::isError($set))
		{
			$r = $set->fetchRow(DB_FETCHMODE_ASSOC);
	
			return $r[0];
		}
	}

	/**
	* getRow. DEPRECATED. Should not be used anymore
	*
	* this is the wrapper itself. query a string, and return the resultobject,
	* or in case of an error, jump to errorpage
	*
	* @param string
	* @return object DB
	*/
	function getRow($sql,$mode = DB_FETCHMODE_OBJECT)
	{
		$set = $this->query($sql);
		$r = $set->fetchRow($mode);
		//$r = $this->db->getrow($sql,$mode);

		$this->handleError($r, "getRow(".$sql.")");
		
		return $r;
	} //end function

} //end Class
?>
