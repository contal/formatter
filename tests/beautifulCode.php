<?php
namespace App\Support;

use App\Models\Admin\Column;
use App\Models\Admin\Conexion;
use App\Models\Admin\ForeignKeys;
use App\Models\Admin\Modelo;
use App\Models\Admin\Table;
use App\Models\Admin\TipoFuncion;
use App\Traits\Nametable;
use Contal\Formatter;
use Illuminate\Support\Facades\Storage;

class CraftModels {

	use Nametable;
	
	public static function all() {
		return Table::all()->transform(function ($table) {
			$id       = Modelo::where('tabla', $table)->pluck('id')->first();
			$name     = self::nameModel($table->get('name'));
			$conexion = config('database.default');
			$columns  = Column::all($table);
			$modelo   = Modelo::find($id);
			return collect(['id' => empty($id) ? null : $id, 'name' => $name, 'table' => $table, 'conexion' => $conexion, 'use' => empty($id) ? [] : $modelo->attributes()->where('tipo_atributo_id', 1)->pluck('contenido') , 'trait' => empty($id) ? [] : $modelo->attributes()->where('tipo_atributo_id', 2)->pluck('contenido') , 'hidden' => empty($id) ? [] : $modelo->attributes()->where('tipo_atributo_id', 3)->pluck('contenido') , 'fillable' => empty($id) ? [] : $modelo->attributes()->where('tipo_atributo_id', 4)->pluck('contenido') , 'appends' => empty($id) ? [] : $modelo->attributes()->where('tipo_atributo_id', 5)->pluck('contenido') , 'methods' => empty($id) ? [] : $modelo->funciones, 'with' => empty($id) ? [] : $modelo->attributes()->where('tipo_atributo_id', 6)->pluck('contenido') , 'exists' => empty($id) ? false : true]);
		});
	}
	
	public static function find($id) {
		return self::all()->where('id', $id)->first();
	}
	
	public static function craft($table) {
		$idConexion      = Conexion::where('nombre', config('database.default'))->pluck('id')->first();
		$connection      = Conexion::find($idConexion);
		$conn_name       = $connection->nombre;
		$namespace       = $connection->namespace;
		$name            = self::nameModel($table);
		$columns         = Column::all($table);
		$has             = ForeignKeys::has($table);
		$belongs         = ForeignKeys::belongs($table);
		$id              = Modelo::where('tabla', $table)->pluck('id')->first();
		$has_content     = "";
		$belongs_content = "";
		$use             = "";
		$trait           = "";
		$hidden          = "";
		$fillable        = "";
		$append          = "";
		$with            = "";
		$function        = "";
		$scope           = "";
		$relacion        = "";
		$accessor        = "";
		$mutator         = "";
		$date            = "";
		$cast            = "";
		
		foreach ($belongs as $data) {
			$clase           = self::singularize($data['referenced_table']);
			$funcion         = camel_case($clase);
			$conexion        = self::getNamespace($data['referenced_connection']);
			$has_content.= "\npublic function {$funcion}() {\nreturn " . '$this->belongsTo' . '(\App\Models\\' . $conexion . '\\' . $clase . '::class, \'' . $data['origin_column'] . '\');' . "\n}";
		};
		
		foreach ($has as $data) {
			$clase    = self::singularize($data['origin_table']);
			$funcion  = camel_case($data['origin_table']);
			$conexion = self::getNamespace($data['origin_connection']);
			$belongs_content.= "\npublic function {$funcion}() {\nreturn " . '$this->hasMany' . '(\App\Models\\' . $conexion . '\\' . $clase . '::class);' . "\n}";
		}
		
		if ($columns->search('deleted_at') != false) {
			$trait.= "SoftDeletes";
			$use.= "use Illuminate\Database\Eloquent\SoftDeletes;\n";
		}
		
		if (empty($id)) {
			$modelo = Modelo::create(['tabla' => $table, 'conexion_id' => $idConexion]);
			
			foreach ($columns->toArray() as $columnas) {
				
				if ($columnas['name'] == 'id' || $columnas['name'] == 'deleted_at' || $columnas['name'] == 'created_at' || $columnas['name'] == 'updated_at' || $columnas['name'] == 'expires_at') {
					continue;
				}
				$fillable.= " '{$columnas['name']}',";
				$modelo->attributes()->create(['tipo_atributo_id'            => 4, 'contenido'            => $columnas['name']]);
			}
		}

		else {
			$modelo     = Modelo::find($id);
			$uses       = $modelo->attributes()->where('tipo_atributo_id', 1)->pluck('contenido');
			$traits     = $modelo->attributes()->where('tipo_atributo_id', 2)->pluck('contenido');
			$hiddens    = $modelo->attributes()->where('tipo_atributo_id', 3)->pluck('contenido');
			$fillables  = $modelo->attributes()->where('tipo_atributo_id', 4)->pluck('contenido');
			$appends    = $modelo->attributes()->where('tipo_atributo_id', 5)->pluck('contenido');
			$withs      = self::with($id)->toArray();
			$functions  = $modelo->funciones()->where('metodo_id', 1)->get();
			$scopes     = $modelo->funciones()->where('metodo_id', 2)->get();
			$relaciones = $modelo->funciones()->where('metodo_id', 3)->get();
			$accessors  = $modelo->funciones()->where('metodo_id', 4)->get();
			$mutators   = $modelo->funciones()->where('metodo_id', 5)->get();
			
			foreach ($uses as $u) {
				$use.= "use {$u};\n";
			}
			$i     = 0;
			$count = count($traits);
			
			foreach ($traits as $k => $t) {
				$i++;
				
				if ($i != $count) {
					$trait.= ", ";
				}
				$trait.= " {$t}";
			}
			
			foreach ($fillables as $g) {
				$fillable.= " '{$g}',";
			}
			
			foreach ($hiddens as $h) {
				$hidden.= " '{$h}',";
			}
			
			foreach ($appends as $a) {
				$append.= " '{$a}',";
			}
			
			foreach ($functions as $f) {
				$tipoFuncion = TipoFuncion::find($f->tipo_funcion_id);
				$function.= "\n" . $tipoFuncion->nombre . " function {$f->nombre}(";
				$i     = 0;
				$count = count($f->parametros);
				
				foreach ($f->parametros as $key => $parametros) {
					$function.= $parametros;
					$i++;
					
					if ($i != $count) {
						$function.= ", ";
					}
				}
				$function.= ") {\n" . $f->contenido . "\n}";
			}
			
			foreach ($scopes as $f) {
				$tipoFuncion = TipoFuncion::find($f->tipo_funcion_id);
				$scope.= "\n" . $tipoFuncion->nombre . " function {$f->nombre}(";
				$i     = 0;
				$count = count($f->parametros);
				
				foreach ($f->parametros as $key => $parametros) {
					$scope.= $parametros;
					$i++;
					
					if ($i != $count) {
						$scope.= ", ";
					}
				}
				$scope.= ") {\n" . $f->contenido . "\n}";
			}
			
			foreach ($relaciones as $f) {
				$tipoFuncion = TipoFuncion::find($f->tipo_funcion_id);
				$relacion.= "\n" . $tipoFuncion->nombre . " function {$f->nombre}(";
				$i     = 0;
				$count = count($f->parametros);
				
				foreach ($f->parametros as $key => $parametros) {
					$relacion.= $parametros;
					$i++;
					
					if ($i != $count) {
						$relacion.= ", ";
					}
				}
				$relacion.= ") {\n" . $f->contenido . "\n}";
			}
			
			foreach ($accessors as $f) {
				$tipoFuncion = TipoFuncion::find($f->tipo_funcion_id);
				$accessor.= "\n" . $tipoFuncion->nombre . " function {$f->nombre}(";
				$i     = 0;
				$count = count($f->parametros);
				
				if ($count > 0) {
					
					foreach ($f->parametros as $key => $parametros) {
						$accessor.= $parametros;
						$i++;
						
						if ($i != $count) {
							$accessor.= ", ";
						}
					}
				}
				$accessor.= ") {\n" . $f->contenido . "\n}";
			}
			
			foreach ($mutators as $f) {
				$tipoFuncion = TipoFuncion::find($f->tipo_funcion_id);
				$mutator.= "\n" . $tipoFuncion->nombre . " function {$f->nombre}(";
				$i     = 0;
				$count = count($f->parametros);
				
				foreach ($f->parametros as $key => $parametros) {
					$mutator.= $parametros;
					$i++;
					
					if ($i != $count) {
						$mutator.= ", ";
					}
				}
				$mutator.= ") {\n" . $f->contenido . "\n}";
			}
			$i     = 0;
			$count = count($withs);
			
			foreach ($withs as $r) {
				$with.= "'{$r}'";
				$i++;
				
				if ($i != $count) {
					$with.= ", ";
				}
			}
			
			foreach (Column::all($table) as $columnas) {
				$col = $columnas->all();
				
				if ($col['type'] == 'date') {
					$date.= "\n'" . $col['name'] . "',";
				}
				elseif ($col['type'] == 'time') {
					$date.= "\n'" . $col['name'] . "',";
				}
				elseif ($col['type'] == 'timestamp') {
					$cast.= "\n'" . $col['name'] . "' => 'timestamp',";
				}
				elseif ($col['type'] == 'datetime') {
					$cast.= "\n'" . $col['name'] . "' => 'datetime',";
				}
				elseif ($col['type'] == 'tinyInteger') {
					$cast.= "\n'" . $col['name'] . "' => 'integer',";
				}
				elseif ($col['type'] == 'smallInteger') {
					$cast.= "\n'" . $col['name'] . "' => 'integer',";
				}
				elseif ($col['type'] == 'mediumInteger') {
					$cast.= "\n'" . $col['name'] . "' => 'integer',";
				}
				elseif ($col['type'] == 'integer') {
					$cast.= "\n'" . $col['name'] . "' => 'integer',";
				}
				elseif ($col['type'] == 'bigInteger') {
					$cast.= "\n'" . $col['name'] . "' => 'integer',";
				}
				elseif ($col['type'] == 'decimal') {
					$cast.= "\n'" . $col['name'] . "' => 'double',";
				}
				elseif ($col['type'] == 'double') {
					$cast.= "\n'" . $col['name'] . "' => 'double',";
				}
				elseif ($col['type'] == 'boolean') {
					$cast.= "\n'" . $col['name'] . "' => 'boolean',";
				}
				elseif ($col['type'] == 'longText') {
					$cast.= "\n'" . $col['name'] . "' => 'array',";
				}
				elseif ($col['type'] == 'json') {
					$cast.= "\n'" . $col['name'] . "' => 'array',";
				}
			};
		}
		
		if (!empty($hidden)) {
			$hidden   = 'protected $hidden = [' . $hidden . '];';
		}
		
		if (!empty($append)) {
			$append   = 'protected $append = [' . $append . '];';
		}
		
		if (!empty($with)) {
			$with     = 'protected $with = [' . $with . '];';
		}
		
		if (!empty($fillable)) {
			$fillable = 'protected $fillable = [' . $fillable . '];';
		}
		
		if (!empty($trait)) {
			$trait    = "\n\nuse" . $trait . ";";
		}
		
		if (!empty($date)) {
			$date     = "\n" . 'protected $dates = [' . $date . "];\n";
		}
		
		if (!empty($cast)) {
			$cast     = "\n" . 'protected $casts  = [' . $cast . "];\n";
		}
		
		if (!empty($function)) {
			$function = "\n    /*------------------------------------------------------------------------------
| Functions
'------------------------------------------------------------------------------*/\n{$function}";
		}
		
		if (!empty($scope)) {
			$scope    = "\n    /*------------------------------------------------------------------------------
| Scopes
'------------------------------------------------------------------------------*/\n{$scope}";
		}
		
		if (!empty($accessor) || !empty($mutator)) {
			$mutator  = "\n    /*------------------------------------------------------------------------------
| Accessors & Mutators
'------------------------------------------------------------------------------*/\n{$mutator} \n {$accessor}";
		}
		$relacion = "\n    /*------------------------------------------------------------------------------
| Relations
'------------------------------------------------------------------------------*/\n{$has_content} \n {$belongs_content} \n {$relacion}";
		$content  = html_entity_decode(view('layouts.models', compact('namespace', 'name', 'conn_name', 'table', 'fillable', 'cast', 'use', 'trait', 'hidden', 'append', 'with', 'date', 'function', 'scope', 'relacion', 'mutator'))->render() , ENT_QUOTES);
		$content  = Formatter::format("<?php\r\n\r\n$content");
		Storage::disk("models")->put("{$namespace}/{$name}.php", $content);
		return self::find($id);
	}
	
	public static function update($id, $data) {
		$modelo = Modelo::find($id);
		$modelo->update($data);
		$modelo->funciones()->delete();
		$modelo->attributes()->delete();
		
		foreach ($data['use'] as $uses) {
			$modelo->attributes()->create(['tipo_atributo_id' => 1, 'contenido' => $uses]);
		}
		
		foreach ($data['trait'] as $traits) {
			$modelo->attributes()->create(['tipo_atributo_id' => 2, 'contenido' => $traits]);
		}
		
		foreach ($data['hidden'] as $hiddens) {
			$modelo->attributes()->create(['tipo_atributo_id' => 3, 'contenido' => $hiddens]);
		}
		
		foreach ($data['fillable'] as $fillables) {
			$modelo->attributes()->create(['tipo_atributo_id' => 4, 'contenido' => $fillables]);
		}
		
		foreach ($data['appends'] as $appends) {
			$modelo->attributes()->create(['tipo_atributo_id' => 5, 'contenido' => $appends]);
		}
		
		foreach ($data['methods'] as $methods) {
			$modelo->funciones()->create($methods);
		}
		return self::craft($modelo->tabla);
	}
	
	public static function delete($id) {
		$modelo     = Modelo::find($id);
		$connection = Conexion::where('nombre', config('database.default'))->first();
		$namespace  = $connection->namespace;
		$name       = self::nameModel($modelo->tabla);
		Modelo::destroy($id);
		Storage::disk('models')->delete("$namespace/$name.php");
		return compact('id');
	}
	
	private static function getNamespace($db) {
		$conn = config("database.connections");
		$keys = array_keys($conn);
		$r    = 0;
		
		foreach ($conn as $key) {
			
			if ($key['database'] == $db) {
				return ucfirst($keys[$r]);
			}
			$r++;
		}
	}
	
	protected static function with($id) {
		$modelos = Modelo::find($id);
		$has     = ForeignKeys::has($modelos->tabla)->transform(function ($values) {
			return camel_case($values['origin_table']);
		})->toArray();
		$belongs = ForeignKeys::belongs($modelos->tabla)->transform(function ($values) {
			return camel_case(self::singularize($values['referenced_table']));
		})->toArray();
		$relaciones = $modelos->funciones()->where('metodo_id', 3)->get()->transform(function ($values) {
			return $values->nombre;
		})->toArray();
		$return = array_merge($relaciones, $belongs);
		$return = array_merge($return, $has);
		return collect($return)->unique();
	}
	
	public static function relaciones($id) {
		$modelos = Modelo::find($id);
		$columns = Column::all($modelos->tabla)->pluck('name')->toArray();
		$has     = ForeignKeys::has($modelos->tabla)->transform(function ($values) {
			return camel_case($values['origin_table']);
		})->toArray();
		$belongs = ForeignKeys::belongs($modelos->tabla)->transform(function ($values) {
			return camel_case(self::singularize($values['referenced_table']));
		})->toArray();
		$relaciones = $modelos->funciones()->where('metodo_id', 3)->get()->transform(function ($values) {
			return $values->nombre;
		})->toArray();
		$return = array_merge($relaciones, $belongs);
		$return = array_merge($return, $has);
		$return = array_merge($return, $columns);
		return collect($return)->unique();
	}
}
