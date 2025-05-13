<?php

namespace Dcat\Admin\Scaffold;

use Dcat\Admin\Exception\AdminException;
use Dcat\Admin\Support\Helper;
use Illuminate\Support\Str;

class ModelCreator
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $tableName;

    /**
     * Model name.
     *
     * @var string
     */
    protected $name;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * ModelCreator constructor.
     *
     * @param  string  $tableName
     * @param  string  $name
     * @param  null  $files
     */
    public function __construct($tableName, $name, $files = null)
    {
        $this->tableName = $tableName;

        $this->name = $name;

        $this->files = $files ?: app('files');
    }

    /**
     * Create a new migration file.
     *
     * @param  string  $keyName
     * @param  bool|true  $timestamps
     * @param  bool|false  $softDeletes
     * @return string
     *
     * @throws \Exception
     */
    public function create($keyName = 'id', $timestamps = true, $softDeletes = false)
    {
        $path = $this->getpath($this->name);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }

        if ($this->files->exists($path)) {
            throw new AdminException("Model [$this->name] already exists!");
        }

        $stub = $this->files->get($this->getStub());

        $stub = $this->replaceClass($stub, $this->name)
            ->replaceNamespace($stub, $this->name)
            ->replaceSoftDeletes($stub, $softDeletes)
            ->replaceDatetimeFormatter($stub)
            ->replaceTable($stub, $this->name)
            ->replaceTimestamp($stub, $timestamps)
            ->replacePrimaryKey($stub, $keyName)
            ->replaceComment($stub)
            ->replaceSpace($stub);

        $this->files->put($path, $stub);
        $this->files->chmod($path, 0777);

        return $path;
    }

    /**
     * Get path for migration file.
     *
     * @param  string  $name
     * @return string
     */
    public function getPath($name)
    {
        return Helper::guessClassFileName($name);
    }

    /**
     * Get namespace of giving class full name.
     *
     * @param  string  $name
     * @return string
     */
    protected function getNamespace($name)
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Replace class dummy.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceClass(&$stub, $name)
    {
        $class = str_replace($this->getNamespace($name).'\\', '', $name);

        $stub = str_replace('DummyClass', $class, $stub);

        return $this;
    }

    /**
     * Replace namespace dummy.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace(
            'DummyNamespace',
            $this->getNamespace($name),
            $stub
        );

        return $this;
    }

    /**
     * Replace soft-deletes dummy.
     *
     * @param  string  $stub
     * @param  bool  $softDeletes
     * @return $this
     */
    protected function replaceSoftDeletes(&$stub, $softDeletes)
    {
        $import = $use = '';

        if ($softDeletes) {
            $import = 'use Illuminate\\Database\\Eloquent\\SoftDeletes;';
            $use = 'use SoftDeletes;';
        }

        $stub = str_replace(['DummyImportSoftDeletesTrait', 'DummyUseSoftDeletesTrait'], [$import, $use], $stub);

        return $this;
    }

    /**
     * Replace datetimeFormatter dummy.
     *
     * @param  string  $stub
     * @param  bool  $softDeletes
     * @return $this
     */
    protected function replaceDatetimeFormatter(&$stub)
    {
        $import = $use = '';

        if (version_compare(app()->version(), '7.0.0') >= 0) {
            $import = 'use Dcat\\Admin\\Traits\\HasDateTimeFormatter;';
            $use = 'use HasDateTimeFormatter;';
        }

        $stub = str_replace(['DummyImportDateTimeFormatterTrait', 'DummyUseDateTimeFormatterTrait'], [$import, $use], $stub);

        return $this;
    }

    /**
     * Replace primarykey dummy.
     *
     * @param  string  $stub
     * @param  string  $keyName
     * @return $this
     */
    protected function replacePrimaryKey(&$stub, $keyName)
    {
        $modelKey = $keyName == 'id' ? '' : "protected \$primaryKey = '$keyName';\n";

        $stub = str_replace('DummyModelKey', $modelKey, $stub);

        return $this;
    }

    /**
     * Replace Table name dummy.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceTable(&$stub, $name)
    {
        $class = str_replace($this->getNamespace($name).'\\', '', $name);

        $table = Str::plural(strtolower($class)) !== $this->tableName ? "protected \$table = '$this->tableName';\n" : '';

        $stub = str_replace('DummyModelTable', $table, $stub);

        return $this;
    }

    /**
     * Replace timestamps dummy.
     *
     * @param  string  $stub
     * @param  bool  $timestamps
     * @return $this
     */
    protected function replaceTimestamp(&$stub, $timestamps)
    {
        $useTimestamps = $timestamps ? '' : "public \$timestamps = false;\n";

        $stub = str_replace('DummyTimestamp', $useTimestamps, $stub);

        return $this;
    }

    /**
     * Replace spaces.
     *
     * @param  string  $stub
     * @return mixed
     */
    public function replaceSpace($stub)
    {
        return str_replace(["\n\n\n", "\n    \n"], ["\n\n", ''], $stub);
    }
    
    /**
     * replace comment.
     * only for table exists.
     * 可在 config/admin.php 中自定义列类型的映射规则 admin.database.column_type_mappings
     *
     * @param $stub
     *
     * @return $this
     */
    public function replaceComment(&$stub): ModelCreator
    {
        $types = [
            'bit'       => 'int',
            'tinyint'   => 'int',
            'smallint'  => 'int',
            'mediumint' => 'int',
            'int'       => 'int',
            'integer'   => 'int',
            'bigint'    => 'int',
    
            'decimal' => 'float',
            'numeric' => 'float',
            'float'   => 'float',
            'double'  => 'float',
    
            'date'      => 'string',
            'time'      => 'string',
            'datetime'  => '\Illuminate\Support\Carbon',
            'timestamp' => '\Illuminate\Support\Carbon',
            'year'      => 'string',
    
            'char'       => 'string',
            'varchar'    => 'string',
            'tinytext'   => 'string',
            'text'       => 'string',
            'mediumtext' => 'string',
            'longtext'   => 'string',
    
            'binary'     => 'string',
            'varbinary'  => 'string',
            'tinyblob'   => 'string',
            'blob'       => 'string',
            'mediumblob' => 'string',
            'longblob'   => 'string',
    
            'enum' => 'string',
            'set'  => 'array',
    
            'json' => 'array',
        ];
        
        // config/admin.php 自定义列类型的映射规则
        $types = array_merge($types, config('admin.database.column_type_mappings', []));
        
        $cols  = Schema::getColumnListing($this->tableName);
        
        $comments = [];
        foreach ($cols as $col) {
            $type        = Schema::getColumnType($this->tableName, $col);
            $typeComment = $types[$type] ?? 'string';
            $comments[]  = " * @property $typeComment $col";
        }
        
        $comment = implode(PHP_EOL, $comments);
        $stub    = $comment
            ? str_replace('DummyComment', $comment, $stub)
            : str_replace(" *\nDummyComment\n", '', $stub);
        $stub    = str_replace('DummyCreatedAt', Carbon::now()->toDateTimeString(), $stub);
        
        return $this;
    }

    /**
     * Get stub path of model.
     *
     * @return string
     */
    public function getStub()
    {
        return __DIR__.'/stubs/model.stub';
    }
}
