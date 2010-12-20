<?
/*
  Copyright (c) 2005 James Baicoianu

  This library is free software; you can redistribute it and/or
  modify it under the terms of the GNU Lesser General Public
  License as published by the Free Software Foundation; either
  version 2.1 of the License, or (at your option) any later version.

  This library is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
  Lesser General Public License for more details.

  You should have received a copy of the GNU Lesser General Public
  License along with this library; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class ComponentManager extends Component {
  public $components = array();
  private $dispatchargs = array();

  protected static $instance;
  public static function singleton($args=NULL) { $name = __CLASS__; if (!self::$instance) { self::$instance = new $name($args); } return self::$instance; }

  function ComponentManager(&$parent) {
    $this->Component("", $parent);
  }

  function Dispatch($page=NULL, $args=NULL) {
    $alternateret = $this->HandleDispatchArgs($args);
    $outputtype = any($args["_output"], (!empty($_SERVER["HTTP_X_AJAX"]) ? "ajax" : "html"));

    if ($args === NULL) {
      $args = $_REQUEST;
    }

    // Load all content URLs from the config, and build a lookup table based on URL
    $cfg = ConfigManager::singleton();
    $contentpages = $cfg->getSetting("page.content");
    foreach($contentpages as $pagename=>$pagevalues) {
      $contenturls[$pagevalues["url"]] = $pagevalues;
      $contenturls[$pagevalues["url"]]["name"] = $pagename;
    }

    $tplmgr = TemplateManager::singleton();

    $ret["type"] = $outputtype;
    $ret["page"] = $page;

    if(!empty($contenturls[$page])) {
      // Check for config-mapped URL first
      $pagevars = $contenturls[$page];
      if(!empty($pagevars["options"])) {
        $cfg->ConfigMerge($cfg->current, $pagevars["options"]);
      }
      $args = $this->ApplyOverrides($args, $applysettings);

      if (!empty($pagevars["component"]) && self::has($pagevars["component"])) {
        $componentargs = (!empty($pagevars["vars"]) ? array_merge($pagevars["vars"], $args) : $args);
        $ret["component"] = $pagevars["component"];
        $ret["content"] = self::fetch($pagevars["component"], $componentargs, $outputtype);
      } else if (!empty($pagevars["template"]) && $tplmgr->template_exists($pagevars["template"])) {
        $pagevars["vars"]["args"] = $args;
        $pagevars["vars"]["sitecfg"] = $this->root->sitecfg;
        $ret["content"] = $this->GetTemplate($pagevars["template"], $pagevars["vars"]);
      } else {
        $ret["content"] = $this->GetTemplate("404.tpl", $pagevars["vars"]);
      }
    } else if ($page == "/") {
      // Handle the homepage
      $ret["component"] = "index";
      $ret["content"] = self::fetch("index", $args, $outputtype);
    } else if (preg_match("|^/((?:[^./]+/?)*)(?:\.(.*))?$|", $page, $m)) {
      // Dispatch directly to a component.  File extension determines output type
      $componentname = str_replace("/", ".", $m[1]);
      $outputtype = any($args["_output"], $m[2], $outputtype);

      $ret["component"] = $componentname;
      $ret["type"] = $outputtype;

      if ($component = $this->Get($componentname)) {
        $componentargs = (!empty($this->dispatchargs[$componentname]) ? array_merge_recursive($args, $this->dispatchargs[$componentname]) : $args);
        $ret["content"] = $component->HandlePayload($componentargs, $outputtype);
      }
    }

    if ($ret['content'] instanceOf ComponentResponse) {
      $output = $ret['content']->getOutput($outputtype);
      $ret['responsetype'] = $output[0];
      $ret['content'] = $output[1];
    }
    // TODO - handle redirects and postprocessing for different output types here
    return $ret;
  }

  function GetDispatchArgs($name, $args=NULL) {
    $ret = $args;
    if (!empty($this->dispatchargs[$name]))
      if ($args === NULL)
        $ret = $this->dispatchargs[$name];
      else {
        $ret = array_merge_recursive($args, $this->dispatchargs[$name]);

        //print_pre("DISPATCH:");
        //print_pre($ret);
      }
    return $ret;
  }
  function HandleDispatchArgs($args) {
    if (!empty($args["_predispatch"])) {
      foreach ($args["_predispatch"] as $k=>$v) {
        if ($dispatchcomponent = $this->Get($v)) {
          $noret = $dispatchcomponent->HandlePayload($args, "dispatch");
        }
      }
    }

    // _dispatchargs are stored and merged in when this component is called later
    if (!empty($args["_dispatchargs"])) {
      foreach ($args["_dispatchargs"] as $k=>$v) {
        // TODO - this currently only works for unique elements (ie, all html.forms on a page).  
        //        We Should check for multidimensionality once placement support is added

        if (!empty($args[$k]))
          $this->dispatchargs[$v][$k] = $args[$k];
      }
    }
  }

  function ApplyOverrides($req, $applysettings=true) {
    $ret = $req;
    // override arguments as specified in the config
    $override_params = ConfigManager::get("search.request.override");
    if(!empty($override_params)) {
      foreach($override_params as $param_key=>$param_values) {
        if (isset($req[$param_key])) {
          $this->ApplySingleOverride($ret, $param_key, $req[$param_key], $param_values, $applysettings);
        }
      }
    }
    //print_pre($ret);
    return $ret;
  }

  function ApplySingleOverride(&$final, $param_key, $param_value=NULL, $param_args=NULL, $applysettings=true) {
    $cfg = ConfigManager::singleton();
    $logmsg = "Applying URL map for $param_key ($param_value)";
    if (!empty($param_args["override"])) {
      $cfg->ConfigMerge($cfg->current, $param_args["override"]);
      $param_args = ConfigManager::fetch("search.request.override.{$param_key}");
    }
    $new_value = NULL;
    if(!empty($param_args["values"][$param_value])) {
      $valuemap = $param_args["values"][$param_value];
      if (is_array($valuemap)) {
        if (!empty($valuemap["override"])) {
          $cfg->ConfigMerge($cfg->current, $valuemap["override"]);
          $param_args = ConfigManager::get("search.request.override.{$param_key}");
        }
        if (isset($valuemap["newvalue"])) {
          $new_value = $valuemap["newvalue"];
          $logmsg .= " (newvalue: '$new_value')";
        } else {
          $new_value = $param_value;
        }
      } else {
        $new_value = $param_args["values"][$param_value];
      }
    } else {
      $new_value = $param_value;
    }
    if(!empty($param_args["key"]) && $new_value !== NULL) {
      array_set($final,$param_args["key"],$new_value);
      //array_unset($final,$param_key);
    }

    if(!empty($param_args["alsooverride"])) {
      $alsolog = "";
      //print_pre($param_values["alsooverride"]);
      foreach ($param_args["alsooverride"] as $also_key=>$also_value) {
        $also_params = ConfigManager::get("search.request.override.{$also_key}");
        if (!empty($also_params)) {
          $alsolog .= (!empty($alsolog) ? "; " : "") . "$also_key=$also_value";
          $this->ApplySingleOverride($final, $also_key, $also_value, $also_params, $applyoverrides);
        }
      }
      if (!empty($alsolog))
        $logmsg .= "(ALSO: $alsolog)";
    }

    if($applysettings && !empty($param_args["settings"])) {
      $settingslog = "";
      $updated_settings = ConfigManager::get("search.request.override.{$param_key}.settings");
      if (!empty($updated_settings)) {
        $flattened_settings = array_flatten($updated_settings);
        $user = User::singleton();
        foreach ($flattened_settings as $setting_key=>$setting_value) {
          $settingslog .= (!empty($settingslog) ? "; " : "") . "$setting_key=$setting_value";
          $user->SetPreference($setting_key, $setting_value, "temporary");
        }
      }
      if (!empty($settingslog))
        $logmsg .= " (SETTINGS: $settingslog)";
    }
    Logger::Info($logmsg);
  }
  static public function fetch($componentname, $args=array(), $output="inline") {
    $ret = NULL;
    $componentmanager = self::singleton();
    $component = $componentmanager->Get($componentname);
    if (!empty($component)) {
      $ret = $component->HandlePayload($args, $output);
      if ($ret instanceOf ComponentResponse) {
        $output = $ret->getOutput($output);
        //$this->root->response["type"] = $output[0];
        $ret = $output[1];
      }
    }
    return $ret;
  }
  static public function has($componentname) {
    $self = self::singleton();
    return $self->HasComponent($componentname);
  }
}

class ComponentResponse implements ArrayAccess {
  public $data = array();
  private $template;
  
  function __construct($template=NULL, $data=NULL) {
    if ($template instanceOf ComponentResponse) {
      $this->template = $template->getTemplate();
      $this->data = $template->data;
    } else {
      $this->template = $template;
      $this->data = $data;
    }
  }
  
  function offsetExists($name) {
    return isset($this->data[$name]);
  }
  function offsetGet($name) {
    return $this->data[$name];
  }
  function offsetSet($name, $value) {
    $this->data[$name] = $value;
  }
  function offsetUnset($name) {
    unset($this->data[$name]);
  }
  function getTemplate() {
    return $this->template;
  }

  function getOutput($type) {
    $ret = array("text/html", NULL);;
    $tplmgr = TemplateManager::singleton();
    switch($type) {
      case 'ajax':
        $ret = array("application/xml", $tplmgr->GenerateXML($this->data));
        break;
      case 'json':
      case 'jsonp':
        $ret = array("application/javascript", $tplmgr->GenerateJavascript($ret, any($_REQUEST["jsonp"], "ajaxlib.blah")));
        break;
      case 'js':
        $ret = array("application/javascript", json_indent(json_encode($this)) . "\n");
        break;
      case 'txt':
        $ret = array("text/plain", $tplmgr->GenerateHTML($tplmgr->GetTemplate($this->template, NULL, $this->data)));
        break;
      case 'xml':
        $ret = array("application/xml", object_to_xml($this, "response"));
        break;
      case 'data':
        $ret = array("", $this->data);
        break;
      case 'componentresponse':
        $ret = array("", $this);
        break;
      case 'html':
      case 'fhtml':
        $framecomponent = any(ConfigManager::get("page.frame"), "html.page");
        $vars["content"] = $this;
        $ret = array("text/html", ComponentManager::fetch($framecomponent, $vars, "inline"));
        break;
      case 'popup': // Popup is same as HTML, but we only use the bare-minimum html.page frame
        $vars["content"] = $this;
        $ret = array("text/html", ComponentManager::fetch("html.page", $vars, "inline"));
        break;
      default:
        $ret = array("text/html", $tplmgr->GetTemplate($this->template, NULL, $this->data));
    }
    if (!empty($this->prefix)) {
      $ret[1] = $this->prefix . $ret[1];
    }
    return $ret;
  }
  function prepend($str) {
    $this->prefix = $str;
  }

  function __toString() {
    $output = $this->getOutput("snip");
    return $output[1];
  }
}
