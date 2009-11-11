<?php
require_once("db.php");
require_once("translation.php");
require_once("option_constraint.php");

class OptionChoice
{
  // Boolean flag to determine if data is present
  private $loaded;

  // Supported option types
  public static $types = array('enum', 'bool', 'int', 'float', 'string', 'password');

  // Support execution models
  public static $execution_types = array('substitution', 'postscript', 'pjl', 'composite', 'forced_composite');

  // This contains the XML data
  public $data = array();

  // Contains of list of objects of respective data
  public $constraint = array();

  public function __construct($id, $data) {
    if ($id == null || $data == null) {
      echo "[ERROR] Failed to construct data... Not enough information\n";
      return false;
    }
    $this->loaded = false;
    $this->data['option_id'] = (string)$id;
    list(,$this->data['id']) = preg_split("/\//", $data['id']);
    if ($data != null) {
      switch((string)gettype($data)) {
      case 'object':
	if (get_class($data) == "SimpleXMLElement") {
	  $this->data['longname'] = (string)$data->ev_longname->en;
	  $this->data['shortname'] = (string)$data->ev_shortname->en;
	  $this->data['driverval'] = (string)$data->ev_driverval;
	  $this->loaded = true;
	}

	break;
      }

      // Prepare the translation data
      if ($this->data['longname']) {
	$this->translation["longname"] = new Translation($data->ev_longname, "option_choice", array("id" => $this->data['id'], "option_id" => $this->data['option_id']), "longname");
      }

      // The choice's constraints
      if ($data->constraints && $data->constraints->constraint) {
	foreach ($data->constraints->constraint as $constraint) {
	  $this->constraint[sizeof($this->constraint)] = new OptionConstraint($this->data['option_id'], $constraint, $this->data['id']);
	}
      }
    }
  }

  public function loadXMLFile($filename) {
    if (!file_exists($filename)) {
      return false;
    }
    $fh = fopen($filename, "r");
    if ($fh) {
      $data = fread($fh, filesize($filename));
      return $this->loadXMLString($data);
    }
    return false;
  }

  /*
   * Initialize class from an XML string
   * @return bool True if initialization was successful
   * @param $data string Contains the XML as a string
   */
  public function loadXMLString($id, $data) {
    $xml = simplexml_load_string($data);
    if (!$xml) {
      return false;
    }

    $this->__construct($id, $xml);

    return $this->loaded;
  }

  public function loadDB($option_id, $id, DB $db = null) {
    if ($id == null) {
      return false;
    }

    if ($db == null) {
      $db = DB::getInstance();
    }

    // Clear any previous data present
    unset($this->constraint);

    $id = mysql_real_escape_string($id);

    // Prepare the query string for extracting main driver details
    $query = "select * from option_choice where option_id=\"$option_id\" and choice_id=\"$id\"";
    $result = $db->query($query);

    if ($result == null) {
      return false;
    }
    $row = mysql_fetch_assoc($result);
    $this->__construct($row);
    mysql_free_result($result);

    // Query string for extracting details about the choice's constraints
    $query = "select * from option_constraint where option_id=\"$id\" and choice_id=\"$id\" and is_choice_constraint=1";
    $result = $db->query($query);

    if ($result) {
      while($row = mysql_fetch_assoc($result)) {
	$this->constraint[sizeof($this->constraint)] = new OptionConstraint($this->data['id'], $row);
      }
    }
    mysql_free_result($result);

    return true;
  }

  public function saveDB(DB $db = null) {
    if ($db == null) {
      $db = DB::getInstance();
    }

    if (!$this->loaded) {
      echo "[ERROR] Data is not loaded...\n";
      return false;
    }

    $this->data['option_id'] = mysql_real_escape_string($this->data['option_id']);
    $this->data['id'] = mysql_real_escape_string($this->data['id']);
    // Find out if there is already an entry present
    $query = "select * from option_choice where option_id=\"{$this->data['option_id']}\" and id=\"{$this->data['id']}\"";
    $result = $db->query($query);
    $count = 0;
    if ($result) {
      $count = mysql_num_rows($result);
      mysql_free_result($result);
    } else {
      echo "[ERROR] Option Choice :: ".$db->getError()."\n";
      return false;
    }

    // Prepare the query string. Update if data exists or insert a new record
    if ($count) {
      $query = "update option_choice set ";
      foreach ($this->data as $key=>$value) {
	$query .= "$key=\"".mysql_real_escape_string($value)."\",";
      }
      $query[strlen($query) - 1] = " ";
      $query .= " where option_id=\"{$this->data['option_id']}\" and id=\"{$this->data['id']}\"";
    } else {
      $query = "insert into option_choice(";
      $fields = $values = "";
      foreach($this->data as $key=>$value) {
	$fields .= "$key,";
	$values .= "\"".mysql_real_escape_string($value)."\",";
      }
      $fields[strlen($fields) - 1] = ')';
      $values[strlen($values) - 1] = ')';
      $query .= $fields." values(".$values;
    }

//		echo "\n**********************************************\n";
//		echo "[QUERY STRING] OptionChoice :: $query\n";
//		echo "\n**********************************************\n";
    $result = $db->query($query);
    if ($result == null) {
      echo "[ERROR] While saving option's choice data...\n".$db->getError()."\n";
      echo "[QUERY STRING] $query\n";
      return false;
    }

    // Trigger the save of constraint data
    if ($this->constraint) {
      foreach ($this->constraint as $constraint) {
	if (!$constraint->saveDB($db)) {
	  print "[ERROR] While saving option's constraint data...\n";
	  return false;
	}
      }
    }

    // Trigger the save of translation data
    if ($this->translation) {
      foreach ($this->translation as $field => $trobj) {
	if (!$trobj->saveDB($db)) {
	  echo "[ERROR] While saving option choice translation data for the \"$field\" field...\n".$db->getError()."\n";
	  return false;
	}
      }
    }

    return true;
  }
}
?>