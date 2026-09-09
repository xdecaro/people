<?php
namespace xdecaro\Component\People\Administrator\Table;
defined('_JEXEC') or die;
use Joomla\CMS\Table\Table;use Joomla\Database\DatabaseDriver;
final class PersonTable extends Table{
 public function __construct(DatabaseDriver $db){parent::__construct('#__xdecaropeople_people','id',$db);}
 public function check():bool{$this->first_name=trim((string)$this->first_name);$this->last_name=trim((string)$this->last_name);$this->display_name=trim((string)$this->display_name);if($this->first_name===''||$this->last_name===''){$this->setError('First name and last name are required.');return false;}if($this->display_name==='')$this->display_name=trim($this->first_name.' '.$this->last_name);foreach(['email','phone','tax_identifier','birth_place','address_line','postal_code','city','region','notes'] as $f){if(property_exists($this,$f)&&$this->{$f}!==null){$v=trim((string)$this->{$f});$this->{$f}=$v!==''?$v:null;}}foreach(['nationality_code','country_code'] as $f){if(property_exists($this,$f)&&$this->{$f}!==null){$v=strtoupper(trim((string)$this->{$f}));$this->{$f}=$v!==''?$v:null;}}return parent::check();}
}
