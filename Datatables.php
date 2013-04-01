<?php

/**
* Laravel Datatable Bundle
*
* This bundle is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
*
* @package    Laravel
* @category   Bundle
* @version    1.3
* @author     Bilal Gultekin <bilal@bilal.im>
*/

class Datatables
{
	public 		$query;
	protected	$query_type;

	protected 	$extra_columns		= array();
	protected 	$excess_columns		= array();
	protected 	$edit_columns		= array();

	public 		$columns 			= array();
	public 		$last_columns 		= array();

	protected	$count_all			= 0;

	protected	$result_object;
	protected	$result_array		= array();
	protected	$result_array_r		= array();


	/**
	 *	Gets query and returns instance of class
	 *
	 *	@return null
	 */
	public static function of($query)
	{
		$ins = with(new static);
		$ins->save_query($query);
		return $ins;
	}

	/**
	 *	Organizes works
	 *
	 *	@return null
	 */

	public function make()
	{
		$this->create_last_columns();
		$this->init();
		$this->get_result();
		$this->init_columns();
		$this->regulate_array();

		return $this->output();
	}


	/**
	 *	Gets results from prepared query
	 *
	 *	@return null
	 */

	private function get_result()
	{
		if($this->query_type == 'eloquent')
		{
			$this->result_object = $this->query->get();
			$this->result_array = array_map(function($object) { return $object->to_array(); }, $this->result_object);
		}
		else
		{
			$this->result_object = $this->query->get();
			$this->result_array = array_map(function($object) { return (array) $object; }, $this->result_object);
		}
	}

	/**
	 *	Prepares variables according to Datatables parameters
	 *
	 *	@return null
	 */

	private function init()
	{
		$this->filtering();
		$this->count();
		$this->paging();
		$this->ordering();
	}


	/**
	 *	Adds extra columns to extra_columns
	 *
	 *	@return $this
	 */

	public function add_column($name,$content,$order = false)
	{
		$this->extra_columns[] = array('name' => $name, 'content' => $content, 'order' => $order);
		return $this;
	}

	/**
	 *	Adds column names to edit_columns
	 *
	 *	@return $this
	 */

	public function edit_column($name,$content)
	{
		$this->edit_columns[] = array('name' => strtolower($name), 'content' => $content);
		return $this;
	}


	/**
	 *	Adds excess columns to excess_columns
	 *
	 *	@return $this
	 */

	public function remove_column()
	{
		$names = func_get_args();
		$this->excess_columns = array_merge($this->excess_columns,$names);
		return $this;
	}


	/**
	 *	Saves given query and determines its type
	 *
	 *	@return null
	 */

	private function save_query($query)
	{
		$this->query = $query;
		$this->query_type = get_class($query) == 'Laravel\Database\Query' ? 'fluent' : 'eloquent';

		$this->columns = $this->query_type == 'eloquent' ? $this->query->table->selects : $this->query->selects;
	}

	/**
	 *	Places extra columns
	 *
	 *	@return null
	 */

	private function init_columns()
	{
		foreach ($this->result_array as $rkey => &$rvalue) {

			foreach ($this->extra_columns as $key => $value) {

				$value['content'] = $this->blader($value['content'],$rvalue);
				$rvalue = $this->include_in_array($value,$rvalue);

			}

			foreach ($this->edit_columns as $key => $value) {
				$value['content'] = $this->blader($value['content'],$rvalue);
				$rvalue[$value['name']] = $value['content'];

			}
		}
	}


	/**
	 *	Converts result_array number indexed array and consider excess columns
	 *
	 *	@return null
	 */

	private function regulate_array()
	{
		foreach ($this->result_array as $key => $value) {
			foreach ($this->excess_columns as $evalue) {
				unset($value[$evalue]);
			}

			$this->result_array_r[] = array_values($value);
		}
	}


	/**
	 *	Creates an array which contains published last columns in sql with their index
	 *
	 *	@return null
	 */

	private function create_last_columns()
	{
		$extra_columns_indexes = array();
		$last_columns = array();
		$count = 0;

		foreach ($this->extra_columns as $key => $value) {
			if($value['order'] === false) continue;
			$extra_columns_indexes[] = $value['order'];
		}

		for ($i=0,$c=count($this->columns);$i<$c;$i++) {

			if(in_array($this->getColumnName($this->columns[$i]), $this->excess_columns))
			{
				continue;
			}

			if(in_array($count, $extra_columns_indexes))
			{
				$count++; $i--; continue;
			}

			$temp = explode(' as ', strtolower($this->columns[$i]));
			$last_columns[$count] = trim(array_pop($temp));
			$count++;
		}

		$this->last_columns = $last_columns;
	}


	/**
	 *	Parses and compiles strings by using Blade Template System
	 *
	 *	@return string
	 */

	private function blader($str,$data = array())
	{
		$parsed_string = Blade::compile_string($str);

		ob_start() and extract($data, EXTR_SKIP);

		try
		{
			eval('?>'.$parsed_string);
		}

		catch (\Exception $e)
		{
			ob_end_clean(); throw $e;
		}

		$str = ob_get_contents();
		ob_end_clean();

		return $str;
	}


	/**
	 *	Places item of extra columns into result_array by care of their order
	 *
	 *	@return null
	 */

	private function include_in_array($item,$array)
	{
		if($item['order'] === false)
		{
			return array_merge($array,array($item['name']=>$item['content']));
		}
		else
		{
			$count = 0;
			$last = $array;
			$first = array();
			foreach ($array as $key => $value) {
				if($count == $item['order'])
				{
					return array_merge($first,array($item['name']=>$item['content']),$last);
				}

				unset($last[$key]);
				$first[$key] = $value;

				$count++;
			}
		}
	}

	/**
	 *	Datatable paging
	 *
	 *	@return null
	 */
	private function paging()
	{
		if(!is_null(Input::get('iDisplayStart')) && Input::get('iDisplayLength') != -1)
		{
			$this->query->skip(Input::get('iDisplayStart'))->take(Input::get('iDisplayLength'));
		}
	}

	/**
	 *	Datatable ordering
	 *
	 *	@return null
	 */
	private function ordering()
	{


		if(!is_null(Input::get('iSortCol_0')))
		{

			for ( $i=0, $c=intval(Input::get('iSortingCols')); $i<$c ; $i++ )
			{
				if ( Input::get('bSortable_'.intval(Input::get('iSortCol_'.$i))) == "true" )
				{
					if(isset($this->last_columns[intval(Input::get('iSortCol_'.$i))]))
					$this->query->order_by($this->last_columns[intval(Input::get('iSortCol_'.$i))],Input::get('sSortDir_'.$i));
				}
			}

		}
	}

	/**
	 *	Datatable filtering
	 *
	 *	@return null
	 */

	private function filtering()
	{

		if (Input::get('sSearch','') != '')
		{
			$copy_this = $this;

			$this->query->where(function($query) use ($copy_this) {

				$db_prefix = $copy_this->database_prefix();

				for ($i=0,$c=count($copy_this->columns);$i<$c;$i++)
				{
					if (Input::get('bSearchable_'.$i) == "true")
					{
						$column = explode(' as ',strtolower($copy_this->columns[$i]));
						$column = array_shift($column);
						$keyword = '%'.Input::get('sSearch').'%';

						if(Config::get('datatables.search.use_wildcards', false)) {
							$keyword = $copy_this->wildcard_like_string(Input::get('sSearch'));
						}

						if(Config::get('datatables.search.case_insensitive', false)) {
							$column = $db_prefix . $column;
							$query->or_where(DB::raw('LOWER('.$column.')'), 'LIKE', $keyword);
						} else {
							$query->or_where($column, 'LIKE', $keyword);
						}
					}
				}
			});

		}

		$db_prefix = $this->database_prefix();

		for ($i=0,$c=count($this->columns);$i<$c;$i++)
		{
			if (Input::get('bSearchable_'.$i) == "true" && Input::get('sSearch_'.$i) != '')
			{
				$keyword = '%'.Input::get('sSearch_'.$i).'%';

				if(Config::get('datatables.search.use_wildcards', false)) {
					$keyword = $copy_this->wildcard_like_string(Input::get('sSearch_'.$i));
				}

				if(Config::get('datatables.search.case_insensitive', false)) {
					$column = $db_prefix . $this->columns[$i];
					$this->query->where(DB::raw('LOWER('.$column.')'),'LIKE', $keyword);
				} else {
					$this->query->where($this->columns[$i], 'LIKE', $keyword);
				}
			}
		}
	}


	/**
	 *  Adds % wildcards to the given string
	 *
	 *  @return string
	 */

	public function wildcard_like_string($str, $lowercase = true) {
	    $wild = '%';
	    $length = strlen($str);
	    if($length) {
	        for ($i=0; $i < $length; $i++) {
	            $wild .= $str[$i].'%';
	        }
	    }
	    if($lowercase) $wild = Str::lower($wild);
	    return $wild;
	}


	/**
	 *  Returns current database prefix
	 *
	 *  @return string
	 */

	public function database_prefix() {
	    return Config::get('database.connections.'.Config::get('database.default').'.prefix', '');
	}


	/**
	 *	Counts current query
	 *
	 *	@return null
	 */

	private function count()
	{
		$copy_query = $this->query;
		$this->count_all = $copy_query->count();
	}


	/**
	 *	Returns column name from <table>.<column>
	 *
	 *	@return null
	 */

	private function getColumnName($str)
	{
		$str = strtolower($str);

		if(strpos($str,' as '))
		{
			$array = explode(' as ', $str);
			return array_pop($array);
		}
		elseif(strpos($str,'.'))
		{
			$array = explode('.', $str);
			return array_pop($array);
		}

		return $str;
	}


	/**
	 *	Prints output
	 *
	 *	@return null
	 */

	private function output()
	{
		$output = array(
			"sEcho" => intval(Input::get('sEcho')),
			"iTotalRecords" => $this->count_all,
			"iTotalDisplayRecords" => $this->count_all,
			"aaData" => $this->result_array_r
		);

		if(Config::get('application.profiler', false)) {
			Log::write('$this->result_array', '<pre>'.print_r($this->result_array, true).'</pre>');
		}
		return Response::json($output);
	}
}
