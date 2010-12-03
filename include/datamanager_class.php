<?
/**
 * class DataManager
 * Abstraction object for any type of queryable data source.  
 * Anything which supports or can emulate the standard CRUD 
 * functions can be used here.  Implements caching layer.
 * @package Framework
 * @subpackage Utils
 */

include_once("include/ormmanager_class.php");

class DataManager {

  var $cfg;
  var $sources;

  function DataManager($cfg=NULL) {
  }

  protected static $instance;
  public static function singleton($args=NULL) {
    $name = __CLASS__;
    if (!self::$instance) {
      if (! empty($args)) {
        self::$instance = new $name($args);
        self::$instance->Init($args);
      } else {
        self::$instance = null;
      }
    }
    return self::$instance;
  }

  function Init(&$cfg) {
    $this->cfg =& $cfg;
    Logger::Info("DataManager initializing");
    //Profiler::StartTimer("DataManager::Init()");

    //Profiler::StartTimer("DataManager::Init() - caches");
    if (!empty($this->cfg->servers["caches"])) {
      foreach ($this->cfg->servers["caches"] as $cachename=>$cachecfg) {
        $this->AddCaches($cachename, $cachecfg);
      }
    }
    //Profiler::StopTimer("DataManager::Init() - caches");

    //Profiler::StartTimer("DataManager::Init() - sources");
    if (!empty($this->cfg->servers["sources"])) {
      foreach ($this->cfg->servers["sources"] as $sourcename=>$sourcecfg) {
        $this->AddSource($sourcename, $sourcecfg);
      }
    }
    //Profiler::StopTimer("DataManager::Init() - sources");

    //include_once("config/outlet-conf.php");

    //$this->outlet = Outlet::getInstance();
    //print_pre($this->sources);
    //Profiler::StopTimer("DataManager::Init()");
  }

  function AddCaches($cachetype, $cfg) {
    $mapping = array("memcache" => "MemcacheCache",
                     "diskcache" => "DiskCache",
                     "apc" => "APCCache",
                     );
    if (!empty($mapping[$cachetype]) && class_exists($mapping[$cachetype])) {
      foreach ($cfg as $cachename=>$cachecfg) {
        //print_pre("add cache $cachename (" . $mapping[$cachetype] . ")");
        //$cachewrapper = call_user_func(array($mapping[$cachetype], "singleton"));

        $this->caches[$cachetype][$cachename] = new $mapping[$cachetype]();
        $this->caches[$cachetype][$cachename]->initCache($cachecfg);
        Logger::Notice("Added cache '$cachetype.$cachename'");
      }
    } else {
      Logger::Debug("Tried to instantiate cache '$cachetype', but couldn't find class");
    }
    
  }
  function AddSource($sourcetype, $cfg) {
    if (!empty($cfg)) {
      //Profiler::StartTimer("DataManager::Init() - Add QPM Server");

      // Check to see if we have a wrapper for this sourcetype in include/datawrappers/*wrapper_class.php
      // If it exists, include the code for it and initialize
      $includefile = "include/datawrappers/" . strtolower($sourcetype) . "wrapper_class.php";
      if (file_exists_in_path($includefile)) {
        include_once($includefile);
        foreach ($cfg as $sourcename=>$sourcecfg) {
          // Server groups get special handling at this level so they can be applied to all types
          if (!empty($sourcecfg["group"]) && ($group = $this->GetGroup($sourcecfg["group"])) !== NULL) {
            Logger::Notice("Merged source group '{$sourcecfg['group']}' into $sourcename");
            $sourcecfg = array_merge_recursive($sourcecfg, $group);
          }
          
          $classname = $sourcetype . "wrapper";
          $sourcewrapper = new $classname($sourcename, $sourcecfg, true);
          if (!empty($sourcecfg["cache"]) && $sourcecfg["cache"] != "none") {
            if ($cacheobj = array_get($this->caches, $sourcecfg["cache"]))
              $sourcewrapper->SetCacheServer($cacheobj, any($sourcecfg["cachepolicy"], true));
          }
          array_set($this->sources, $sourcetype.".".$sourcename, $sourcewrapper);
          Logger::Notice("Added source '$sourcetype.$sourcename': " . $sourcecfg["host"]);
        }
      } else {
        Logger::Debug("Tried to instantiate source '$sourcetype', but couldn't find class");
      }
      //Profiler::StopTimer("DataManager::Init() - Add QPM Server");
    }
  }

  /** 
   * Determine source for a given id, then pass on all arguments to wrapper
   *
   * @param string $id (query identifier)
   * @param any $query
   * @param any $args
   * @param any $extras
   * @return object resultset
   **/

  static function &Query($id, $query, $args=NULL, $extras=NULL) {
    global $webapp;
    
    //Profiler::StartTimer("DataManager::Query()");
    $result = NULL;

    $queryid = new DatamanagerQueryID($id);

    $source = DataManager::PickSource($queryid);
    if (!$source) {
      Logger::Error("Unable to determine source to serve request: %s", $queryid->name);
    }

    // Pull default caching policy from connection object, then force enabled/disable as requested
    $cache = $source->cachepolicy;
    if (!empty($queryid->args["nocache"]))
      $cache = false;
    if (!empty($queryid->args["cache"])) {
      $cache = any($source->cachepolicy, true);
    }
    // If the query string contains nocache, force to no cache
    if (!empty($webapp->request["args"]["nocache"])) {
      $cache = false;
    }

    $foundincache = false;
    if ($cache) {
      //Profiler::StartTimer("DataManager::Query() - Check cache");
      if (($cacheresult = $source->CacheGet($queryid, $query, $args)) !== NULL) {
        if ($cacheresult && !$cacheresult->isExpired()) {
          $result = $cacheresult->getPayload(false);
          $foundincache = true;
        }
      }
      //Profiler::StopTimer("DataManager::Query() - Check cache");
    }

    //Logger::Error("cache for $id: $cache");
    //Logger::Error("is result false ? " . ($result === false));
    if ($result === NULL && empty($queryid->args["nosource"])) {
      //Profiler::StartTimer("DataManager::Query() - Check Original Source");

      // We failed to retrieve anything from the cache - perform the real query
      if ($source) {
        $result = $source->query($queryid, $query, $args, $extras);
        //print_pre("replace $resourcename ($foundincache)");
        if ($cache && !$foundincache) {
          if (!empty($result)) // Only cache "positive" responses (FIXME - maybe this should be an option?  We should observe and make sure this change doesn't hurt more than it helps)
            $source->CacheSet($queryid, $query, $args, $result);
          /*
          else
            Logger::Error("Tried to cache empty dataset for key '{$queryid->name}' (query: $query, args: " . print_ln($args, true) . ")");
          */
        }
      }

      //Profiler::StopTimer("DataManager::Query() - Check Original Source");
    }

    // If result is STILL null, and we're in soft-expire-cache mode, let's use the expired data and throw a warning
    if ($result === NULL) {
      if ($cache == 2 && $cacheresult instanceOf CacheEntry) {
        $result = $cacheresult->getPayload(true);
        Logger::Warn("Cache for '%s' was soft-expired but we couldn't refresh it (%d seconds stale)", $queryid->name, (time() - $cacheresult->timestamp) - $cacheresult->timeout);
      }

    }

    //Profiler::StopTimer("DataManager::Query()");
    return $result;
  }

  /**
   * This function perform an insert query into table.
   *
   * @param string $id (resource id)
   * @param string $table
   * @param array $values
   * @return int (last insert id)
   */
  static function &QueryInsert($id, $table, $values, $extra=NULL) {
    //Profiler::StartTimer("DataManager::QueryInsert()");
    $insert_id = NULL;
    $queryid = new DatamanagerQueryID($id);
    if ($source =& DataManager::PickSource($queryid)) {
      $insert_id = $source->QueryInsert($queryid, $table, $values, $extra);
    }
    //Profiler::StopTimer("DataManager::QueryInsert()");
    return $insert_id;
  }

  /**
   * This function perform an update query on a row in the specified table.
   *
   * @param string $id (resource id)
   * @param string $table
   * @param array $values
   * @param array $where_condition
   * @return int (last insert id)
   */
  static function &QueryUpdate($id, $table, $values, $where_condition, $bind_vars=array()) {
    //Profiler::StartTimer("DataManager::QueryUpdate()");
    $rows_affected = NULL;
    $queryid = new DatamanagerQueryID($id);
    if ($source =& DataManager::PickSource($queryid)) {
      $rows_affected = $source->QueryUpdate($queryid, $table, $values, $where_condition, $bind_vars);
    }
    //Profiler::StopTimer("DataManager::QueryUpdate()");
    return $rows_affected;
  }

  /**
   * This function performs a delete query on a row in the specified table.
   *
   * @param string $id (resource id)
   * @param string $table
   * @param array $values
   * @param array $where_condition
   * @return int (last insert id)
   */
  static function &QueryDelete($id, $table, $where_condition=NULL, $bind_vars=array()) {
    //Profiler::StartTimer("DataManager::QueryUpdate()");
    $rows_affected = NULL;
    $queryid = new DatamanagerQueryID($id);
    if ($source =& DataManager::PickSource($queryid)) {
      $rows_affected = $source->QueryDelete($queryid, $table, $where_condition, $bind_vars);
    }
    //Profiler::StopTimer("DataManager::QueryUpdate()");
    return $rows_affected;
  }

  /**
   * This function perform a create query for the specified table.
   *
   * @param string $id (resource id)
   * @param string $table
   * @param array $values
   * @param array $where_condition
   * @return int (last insert id)
   */
  static function &QueryCreate($id, $table, $columns) {
    Profiler::StartTimer("DataManager::QueryCreate()", 1);
    $rows_affected = NULL;
    $queryid = new DatamanagerQueryID($id);
    if ($source =& DataManager::PickSource($queryid)) {
      $rows_affected = $source->QueryCreate($queryid, $table, $columns);
    }
    Profiler::StopTimer("DataManager::QueryCreate()");
    return $rows_affected;
  }
  
  function CacheClear($id) {
    $queryid = new DatamanagerQueryID($id);
    if ($source =& DataManager::PickSource($queryid)) {
      $source->CacheClear($queryid);
    } else if ($this->caches["memcache"]["data"] !== NULL) {
      Logger::Notice("Forcing deletion of memcache item '%s'", $queryid->name);
      $this->caches["memcache"]["data"]->delete($queryid->name);
    }
  }
  function CacheFlush() {
    if ($this->caches["memcache"]["data"]) {
      Logger::Error("Flushing all memcache data");
      $this->caches["memcache"]["data"]->flush();
      return true;
    }
    return false;
  }
  static function &PickSource($queryid) {
    //Profiler::StartTimer("DataManager::PickSource()");

    $chosensource = NULL;

    $parts = explode(".", $queryid->name);
    $data = self::singleton();
    $sources =& $data->sources;
    foreach ($parts as $num=>$part) {
      if (!empty($sources[$part])) {
        if (is_array($sources[$part])) {
          $sources =& $sources[$part];
        } else if (is_subclass_of($sources[$part], "connectionwrapper")) {
          $chosensource =& $sources[$part];
          break;
        }
      } else {
          break;
      }
    }

    if ($chosensource === NULL && !empty($data->sources[$parts[0]]) && !empty($data->sources[$parts[0]]["default"]))
      $chosensource =& $data->sources[$parts[0]]["default"];

    //Profiler::StopTimer("DataManager::PickSource()");
    return $chosensource;
  }

  function GetGroup($groupname) {
    $ret = NULL;
    if (!empty($this->cfg->servers["groups"][$groupname])) {
      $ret = $this->cfg->servers["groups"][$groupname];
      if (!empty($ret["bucketcfg"])) {
        $fname = $this->cfg->locations["config"] . '/' . $ret["bucketcfg"];
        if (file_exists($fname)) {
          $lines = file($fname);
          $bucketnum = 0;
          foreach ($lines as $line) {
            $parts = explode(": ", trim($line));
            if ($parts[0] == "servers") {
              $servers = explode(", ", $parts[1]);
              for ($i = 0; $i < count($servers); $i++) {
                $ret["servers"][$i]["host"] = $servers[$i];
              }
            } else if ($parts[0] == "bucket") {
              $bucketinfo = explode(", ", $parts[1]);
              $bucketname = array_shift($bucketinfo);
              $ret["buckets"][$bucketnum++] = array("name" => $bucketname, "servers" => $bucketinfo);
            }
          }
        }
      }
    }
    //print_pre($ret);
    return $ret;
  }

  function Quit() {
    $this->CloseAll($this->sources);
  }
  function CloseAll(&$sources) {
    foreach ($sources as $source) {
      if (is_array($source)) {
        $this->CloseAll($source);
      } else if (is_subclass_of($source, "connectionwrapper")) {
        $source->Close();
      }
    }

  }

  function LoadModel($model) {
    $ormmgr = OrmManager::singleton();
    $ormmgr->LoadModel($model);
  }
}

/**
 * class DataManagerQueryID
 * Query identifier, including name, hash, and args
 * @package Framework
 * @subpackage Utils
 */
class DataManagerQueryID {
  public $id;
  public $name;
  public $hash;
  public $args;

  function __construct($idstr=NULL) {
    if ($idstr !== NULL)
      $this->parse($idstr);
  }
  function parse($id) {
    $this->id = $id;
    if (preg_match("/^((.*?)(?:\#(.*?))?)(?:\:(.*?))?$/", $id, $m)) {
      $this->id = $m[1];
      if (!empty($m[2]))
        $this->name = $m[2];
      if (!empty($m[3]))
        $this->hash = $m[3];
      if (!empty($m[4])) {
        $rargs = explode(";", $m[4]);
        $this->args = array();
        foreach ($rargs as $rarg) {
          if (strpos($rarg, "=") !== false) {
            list($k, $v) = explode("=", $rarg, 2);
            $this->args[$k] = $v;
          } else {
            $this->args[$rarg] = true;
          }
        }
      }
    }
  }
}
