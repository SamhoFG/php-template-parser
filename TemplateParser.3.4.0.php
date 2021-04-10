<?php
/*	
	V3.4.0 Updated by Sam
	Updated:	New display tag - <display_mod!:int_int>. e.g. <display_mod!:3_0> - the non-first loop will be displayed every 3 loops
				Fix function - getReplaceTagStr($str, $_tag, $_bool)
	V3.3.2 Updated by Sam
	Updated:	New function behaviour - int setKey($_section, $_key, $_value) return the number of the replacement.
				Fix function - checkLoopCount($_section)
	V3.3.1 Updated by Sam
	Updated:	Important bugs Fix
				Fix related to:
					SetKey,
					All kind of DisplayTag functions,
					wildcard (*),
					Loop
	V3.3 Updated by Sam
	Updated:	New display tag - <display_last>.
				New private function - prepareLoop($_section).
				New private function - finishParseLoop($_section, $last).
	V3.2 Updated by Sam
	Updated:	New display tag - <display_mod:int_int>.  e.g. <display_mod:3_0> - the first loop will be displayed every 3 loops
				Remove display tag - <display_3_0>, <display_3_1>, <display_3_2>.  Please use the new tag <display_mod:3_0>, <display_mod:3_1>, <display_mod:3_2>
				Remove private function - displayOneThird($_section), displayTwoThird($_section), displayThreeThird($_section).  Please use the new function - displayLoopTag($_section, $_key, $_bool) instead.
				New private function - displayMod($_section).
				New display tag - <empty_loop!:key>.  <empty_loop!:key> need to be used with <loop:key>.  If <loop:key> is not empty, <empty_loop!:key> will be shown.
	V3.1.1 Updated by Sam
	Updated:	Fix function - getString()
				Fix function - printOut()
	V3.1 Updated by Sam
	Updated:	New key tag - <!-- loop_index --> in loop will be set with 0-index.
				New behaviour - <!-- loop_count --> in loop will be set with 1-index.
	V3.0 Updated by Sam
	Updated:	Remove function - displayFinTag($_section, $_key, $_bool).  Please use the new function - displayLoopTag($_section, $_key, $_bool) instead.
				New function - displayLoopTag($_section, $_key, $_bool).  Work Like displayTag, but apply to all instance in a loop, including all instance that will be created.
				New display tag - <display_key:key>. ex. <display_key:msg><!-- msg --><display_key:msg>.  If the key, 'msg', is set, <display_key:msg> will be display.
				wildcard (*) is supported in all display tag function. ex. displayTag($_section, 'p*y', $_bool) will display any tag that start with p and end with y, like pay and play.
				New behaviour - when loop ends, any leftover <display:key> tag will be hidden and any leftover <display!:key> tag will be showed.
	V2.4 Updated by Sam
	Updated:	New function - displayFinTag($_section, $_key, $_bool).  Work Like displayTag, but apply to all instance in a loop, but not including all instance that will be created.
	V2.3 Updated by Sam
	Updated:	New display tag - <empty_loop:key>.  <empty_loop:key> need to be used with <loop:key>.  If <loop:key> is empty, <empty_loop:key> will be shown.
	V2.2 Updated by Sam
	Updated:	New display tag - <display_odd>, <display_even>.
	V2.1 Updated by Sam
	Updated:	New display tag - <display!:key>, <display_first>. New key tag - <!-- loop_count --> in loop will be set with 0-index.
	V2.0 Updated by Sam
	Updated:	Support loop in loop.
*/

class TemplateParser {

	var $fin_pair = array();

	var $pro_pair = array();

	var $pro_prepare = array();

	var $ori_pair = array();

	var $children = array();

	var $loop_count = array();

	var $file_path;

	//var $file_str;

	

	function TemplateParser($_path) {

		$this->file_path = $_path;

		// Get file string
		$this->ori_pair["root"]  = file_get_contents($this->file_path);
		/*$file = file($this->file_path);
		reset($file);

		$file_str = "";
		for ($i = 0; $i < count($file); $i++) {

			$file_str .= $file[$i];

		}

		$this->ori_pair["root"] = $file_str;
		$file_str = "";*/

		$this->checkLoop("root");
		$this->pro_pair["root"] = NULL;
		$this->pro_prepare["root"] = false;
		$this->fin_pair["root"] = NULL;
		$this->loop_count["root"] = 0;

	}

	function checkLoop($_section) {

		// Get section string
		$_section_str = $this->ori_pair[$_section];

		// Filter out <loop:xxx>...</loop:xxx>
		$start_pos = strpos($_section_str, "<loop:");

		$this->children[$_section] = array();

		while ( $start_pos !== false ) {

			// Find loop start tag
			$temp_pos = strpos($_section_str, ">", $start_pos + 6);	// "<loop:" has 6 chars
			if ($temp_pos === false) die("Can't initial loop");

			// Set new section name
			$section_name = trim( substr($_section_str, $start_pos + 6, $temp_pos - $start_pos - 6) );

			// Find loop end tag
			$close_tag = "</loop:".$section_name.">";
			$end_pos = strpos($_section_str, $close_tag, $start_pos);
			if ($end_pos === false) die("Can't find loop end, ".$close_tag);

			// Set tree links
			$this->children[$_section][] = $section_name;

			// Set new section string
			$section = substr($_section_str, $temp_pos + 1, $end_pos - $temp_pos - 1);
			$this->ori_pair[$section_name] = $section;
			$this->pro_pair[$section_name] = NULL;
			$this->pro_prepare[$section_name] = false;
			$this->fin_pair[$section_name] = NULL;

			// Set first loop
			$this->loop_count[$section_name] = 0;


			// Update parent section string
			$_section_str = substr($_section_str, 0, $start_pos)."<!-- ".$section_name." -->".substr($_section_str, $end_pos + strlen($close_tag));

			$this->checkLoop($section_name);

			// Find next loop tag
			$start_pos = strpos($_section_str, "<loop:", $start_pos++);

		}

		// Set parent section string
		$this->ori_pair[$_section] = $_section_str;

	}

	function checkLoopCount($_section) {
		$count = $this->loop_count[$_section];
		if($this->pro_prepare[$_section]) $count++;
		return $count;
	}

	function setKey($_section, $_key, $_value) {
		// define counter
		$count = 0;

		// normalize input value
		$_section = strval($_section);
		$_key = strval($_key);
		$_value = strval($_value);
		$this->replaceTag($_section, "display_key:".$_key, !empty($_value));
		$this->replaceTag($_section, "display_key!:".$_key, empty($_value));

		if(isset($this->pro_pair[$_section])) {
			$section = $this->pro_pair[$_section];
			$section = str_replace("<!-- ".$_key." -->", $_value, $section, $count);
			$this->pro_pair[$_section] = $section;
		}
		return $count;
	}

	private function getReplaceTagStr($str, $_tag, $_bool) {
		// Check wildcard
		$_tag = preg_replace('#\*+#', '.+?', $_tag);
		
		// Set Pattern
		$tag_pattern = '#\<'.$_tag.'\>(.*?)\</'.$_tag.'\>#s';
		if(preg_match_all($tag_pattern, $str, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) != false) {
			foreach($matches as $match) {
				if ($_bool) {
					$str = str_replace($match[0][0],$match[1][0],$str);
				} else {
					$str = str_replace($match[0][0],'',$str);
				}
			}
		}
		return $str;
	}

	private function getReplacePair($_section, $source = array('pro_pair','ori_pair'), $target = 'pro_pair', $useIndex = false) {
		if(!is_array($source)) $source = array($source);
		if(!is_array($target)) $target = array($target);
		$pairs = array();
		if($useIndex) {
			$keys = array(0, 1);
		} else {
			$keys = array('source', 'target');
		}
		if(count($target) > 1) {
			// replace from 1 to 1
			for($i=0;!is_null($source[$i]) && !is_null($target[$i]);$i++) {
				if (!is_null($this->{$source[$i]}[$_section])) {
					$pairs[] = array($keys[0] => $source[$i], $keys[1] => $target[$i]);
				}
			}
		} else {
			// replace with source fall back
			// Get Source String
			for($i=0;isset($source[$i]);$i++) {
				if (isset($this->{$source[$i]}[$_section])) {
					$pairs[0][$keys[0]] = $source[$i];
					break;
				}
			}
			if (!empty($pairs[0][$keys[0]])) {
				$pairs[0][$keys[1]] = $target[0];
			}
		}
		return $pairs;
	}

	private function replaceTag($_section, $_tag, $_bool, $source = array('pro_pair','ori_pair'), $target = 'pro_pair') {
		if(isset($this->pro_prepare[$_section]) && $this->pro_prepare[$_section]) $this->finishParseLoop($_section);
		$replace_pairs = $this->getReplacePair($_section, $source, $target);
		if(!empty($replace_pairs)) {
			foreach($replace_pairs as $replace_pair) {
				$this->{$replace_pair['target']}[$_section] = $this->getReplaceTagStr($this->{$replace_pair['source']}[$_section], $_tag, $_bool);
			}
		}
	}

	private function replaceLoopTag($_section, $_tag, $_bool) {
		$this->replaceTag($_section, $_tag, $_bool, array('ori_pair','pro_pair','fin_pair'), array('ori_pair','pro_pair','fin_pair'));
	}

	private function replaceProTag($_section, $_tag, $_bool) {
		$this->replaceTag($_section, $_tag, $_bool, 'pro_pair', 'pro_pair');
	}

	function displayTag($_section, $_key, $_bool) {
		$this->replaceTag($_section, "display:".$_key, $_bool);
		$this->replaceTag($_section, "display!:".$_key, !$_bool);
	}

	private function displayFirstLoop($_section) {
		$this->replaceProTag($_section, "display_first", $this->loop_count[$_section] == 0);
	}

	private function displayOddLoop($_section) {
		$this->replaceProTag($_section, "display_odd", $this->loop_count[$_section] % 2 == 1);
	}

	private function displayEvenLoop($_section) {
		$this->replaceProTag($_section, "display_even", $this->loop_count[$_section] % 2 == 0);
	}

	private function displayMod($_section) {
		$tag_pattern = '#\<(display_mod:([1-9]{1}[0-9]*)_(0|[1-9]{1}[0-9]*))\>#s';
		$replace_pairs = $this->getReplacePair($_section);
		$str = $this->{$replace_pairs[0]['source']}[$_section];
		if(preg_match_all($tag_pattern, $str, $matches, PREG_SET_ORDER) !== false && !empty($matches)) {
			foreach($matches as $match) {
				$this->replaceProTag($_section, $match[1], $this->loop_count[$_section] % (intval($match[2])) == (intval($match[3])));
			}
		}
		$tag_pattern = '#\<(display_mod!:([1-9]{1}[0-9]*)_(0|[1-9]{1}[0-9]*))\>#s';
		$replace_pairs = $this->getReplacePair($_section);
		$str = $this->{$replace_pairs[0]['source']}[$_section];
		if(preg_match_all($tag_pattern, $str, $matches, PREG_SET_ORDER) !== false && !empty($matches)) {
			foreach($matches as $match) {
				$this->replaceProTag($_section, $match[1], $this->loop_count[$_section] % (intval($match[2])) !== (intval($match[3])));
			}
		}
	}

	function displayLoopTag($_section, $_key, $_bool) {
		$this->replaceLoopTag($_section, "display:".$_key, $_bool);
		$this->replaceLoopTag($_section, "display!:".$_key, !$_bool);
	}

	private function displayAllTag($_section, $_bool) {
		/*$this->replaceProTag($_section, "display*!:*", !$_bool);
		$this->replaceProTag($_section, "display*", $_bool);*/
		$this->replaceProTag($_section, "display:*", $_bool);
		$this->replaceProTag($_section, "display!:*", !$_bool);
		$this->replaceProTag($_section, "display_key:*", $_bool);
		$this->replaceProTag($_section, "display_key!:*", !$_bool);
		$this->replaceProTag($_section, "display_*", $_bool);
	}

	private function displayEmptyLoop($_section, $_key) {
		$this->replaceProTag($_section, "empty_loop:".$_key, $this->loop_count[$_key] == 0);
		$this->replaceProTag($_section, "empty_loop!:".$_key, $this->loop_count[$_key] > 0);
	}

	function setLoop($_section, $_value) {
		$this->pro_pair[$_section] = $_value;

	}

	function parseLoop($_section, $last=false) {
		//echo 'parseLoop: ';
		//echo $_section.' -> '.$this->loop_count[$_section].(($last)?', last':'').'<br/>';
		if(!$this->pro_prepare[$_section]) {
			$this->prepareLoop($_section);
			$this->pro_prepare[$_section] = true;
			if($last) $this->finishParseLoop($_section, true);
		} else {
			$this->finishParseLoop($_section, $last);
			if(!$last) $this->parseLoop($_section);
		}
	}

	private function prepareLoop($_section) {
		//echo 'prepareLoop: ';
		//echo $_section.' -> '.$this->loop_count[$_section].'<br/>';
		$this->replace($_section);
		if(!empty($this->pro_pair[$_section])) {
			$this->displayFirstLoop($_section);
			$this->displayOddLoop($_section);
			$this->displayEvenLoop($_section);
			$this->displayMod($_section);
			$this->displayAllTag($_section, false);
			$this->setKey($_section, "loop_index", $this->loop_count[$_section]);
			$this->setKey($_section, "loop_count", $this->loop_count[$_section]+1);
		}
	}

	private function finishParseLoop($_section, $last=false) {
		//echo 'finishParseLoop: ';
		//echo $_section.' -> '.$this->loop_count[$_section].(($last)?', last':'').'<br/>';
		if($this->pro_prepare[$_section]) {
			$this->pro_prepare[$_section] = false;
			if(!empty($this->pro_pair[$_section])) {
				$this->replaceProTag($_section, "display_last", $last); 
				$this->fin_pair[$_section] .= $this->pro_pair[$_section];
			}
			if(!empty($this->pro_pair[$_section]) || !$last) $this->loop_count[$_section]++;
			$this->pro_pair[$_section] = NULL;
		}
	}

	private function replace($_section) {
		if ($this->children[$_section]) {
			foreach($this->children[$_section] as $section) {
				$this->parseLoop($section, true); // Parse Last Loop
				$this->displayEmptyLoop($_section, $section);
				if ($this->fin_pair[$section]) {
					$this->setKey($_section, $section, $this->fin_pair[$section]);
				}
				$this->fin_pair[$section] = NULL;
				//echo $_section.' -> '.$section.': '.$this->loop_count[$section].'<br/>';
				$this->loop_count[$section] = 0;
			} 
		}
	}

	function getString() {

		$this->parseLoop("root", true);
		return $this->fin_pair["root"];

	}

	function printOut() {

		$this->parseLoop("root", true);
		echo($this->fin_pair["root"]);

	}

}

?>