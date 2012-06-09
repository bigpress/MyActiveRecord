<?php


class MyValidator {



	/**
	 * Validates that an attribute has a value, adding an error
	 * to the object if the value is empty.
	 *
	 * @param	string	strKey	name of field/attribute/key
	 * @param	string	strMessage	Error message to record if value does not match
	 * @return	boolean	True if the field has a value. False if it does not.
	 */
	function validate_existence($strKey, $strMessage=null)
	{
		if( !empty($this->$strKey) )
		{
			return true;
		}
		else
		{
			$this->add_error($strKey, $strMessage ? $strMessage : 'Missing '.$strKey);
			return false;
		}
	}

	/**
	 * Validates the value of an attribute against a regular
	 * expression, adding an error to the object if the value
	 * does not match.
	 *
	 * @param	string	strKey	name of field/attribute/key
	 * @param	string	strRegExp	Regular Expression
	 * @param	string	strMessage	Error message to record if value does not match
	 * @return	boolean	True if the field matches. False if it does not match.
	 */
	function validate_regexp($strKey, $strRegExp, $strMessage=null)
	{
		if( preg_match($strRegExp, $this->$strKey) )
		{
			return true;
		}
		else
		{
			$this->add_error($strKey, $strMessage ? $strMessage : 'Invalid '.$strKey);
			return false;
		}
	}

	/**
	 * Validates the uniqueness of the value of a given field/key.
	 * Adds error to object if field is not unique
	 * 
	 * @param	string	strKey	name of field/attribute/key
	 * @param	string	strMessage	Error message to record if value is not unique
	 * @return	boolean true if field is unique, false if not
	*/
	function validate_uniqueness_of($strKey, $strMessage=null)
	{
		if ( empty($this->id) && MyActiveRecord::Count( get_class($this), "$strKey = '{$this->$strKey}'" ) > 0 )
		{
			$this->add_error($strKey, $strMessage ? $strMessage : ucfirst($strKey).' is not unique');
			return false;
		}elseif(MyActiveRecord::Count( get_class($this), "$strKey = '{$this->$strKey}' AND id != " . ($this->id + 0) ) > 0){
			$this->add_error($strKey, $strMessage ? $strMessage : ucfirst($strKey).' is not unique');
			return false;
		}else{
			return true;
		}		
	}

	/**
	 * Checks to see if an e-mail exists, looks like an e-mail, and is unique
	 *
	 * @param string $strKey 
	 * @return string
	 * @author Walter Lee Davis
	 */

	function validate_unique_email($strKey){
		return $this->validate_existence($strKey,'Please enter your e-mail address') &&
			$this->validate_regexp($strKey,"/^[a-z0-9\._-]+@+[a-z0-9\._-]+\.+[a-z]{2,6}$/i", 'That didn&rsquo;t look like an e-mail address') &&
			$this->validate_uniqueness_of($strKey,'This e-mail address is already registered');		
	}

	/**
	 * Checks to see if an e-mail exists and looks like an e-mail
	 *
	 * @param string $strKey 
	 * @return string
	 * @author Walter Lee Davis
	 */

	function validate_email($strKey){
		return $this->validate_existence($strKey,'Please enter your e-mail address') &&
			$this->validate_regexp($strKey,"/^[a-z0-9\._-]+@+[a-z0-9\._-]+\.+[a-z]{2,6}$/i", 'That didn&rsquo;t look like an e-mail address');		
	}
}