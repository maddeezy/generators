<?php

namespace Bpocallaghan\Generators\Commands;

use Symfony\Component\Console\Input\InputOption;

class FileCommand extends GeneratorCommand
{

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'generate:file';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a file from a stub in the config';

	/**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'File';

	/**
	 * Find the type's settings and set local var
	 */
	private function setSettings()
	{
		$type = $this->option('type');
		$options = config('generators.settings');

		$found = false;
		// loop through the settings and find the type key
		foreach ($options as $key => $settings)
		{
			if ($type == $key)
			{
				$found = true;
				break;
			}
		}

		if ($found === false)
		{
			$this->error('Oops!, we could not find the type in the settings to generate from');
			exit;
		}

		// set the default keys and values if they do not exist
		$defaults = config('generators.defaults');
		foreach ($defaults as $key => $value)
		{
			if (!isset($settings[$key]))
			{
				$settings[$key] = $defaults[$key];
			}
		}

		$this->settings = $settings;
	}

	/**
	 * Get the filename of the file to generate
	 *
	 * @return string
	 */
	private function getFileName()
	{
		$name = $this->getArgumentNameOnly();

		switch ($this->option('type'))
		{
			case 'view':
				$name = ($this->option('view-name') ? $this->option('view-name') : $name);
				break;
			case 'model':
				$name = $this->getModelName($this->url);
				break;
			case 'controller':
				$name = $this->getControllerName($name);
				break;
			case 'seed':
				$name = $this->getSeedName($name);
				break;
		}

		return $this->settings['prefix'] . $name . $this->settings['postfix'] . $this->settings['file_type'];
	}

	/**
	 * Get the full namespace name for a given class.
	 *
	 * @param  string $name
	 * @param bool    $withApp
	 * @return string
	 */
	protected function getNamespace($name, $withApp = true)
	{
		$path = str_replace('/', '\\', $this->getArgumentPath()) . $this->settings['namespace'];

		$pieces = array_map('ucfirst', explode('/', $path));

		$namespace = ($withApp === true ? $this->getAppNamespace() : '') . implode('\\', $pieces);

		$namespace = rtrim(ltrim(str_replace('\\\\', '\\', $namespace), '\\'), '\\');

		return $namespace;
	}

	protected function getClassName()
	{
		return str_replace([$this->settings['file_type']], [''], $this->getFileName());
	}

	protected function getUrl()
	{
		return '/' . rtrim(implode('/', array_map('strtolower', explode('/', $this->getArgumentPath(true)))), '/');
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->setSettings();
		$this->url = $this->getUrl();

		$path = $this->getPath('');
		if ($this->files->exists($path) && $this->option('force') === false)
		{
			return $this->error($this->type . ' already exists!');
		}

		$this->makeDirectory($path);

		$this->files->put($path, $this->buildClass($this->getArgumentName()));

		$this->info(ucfirst($this->option('type')) . ' created successfully.');
		$this->info('- ' . $path);

		if ($this->settings['dump_autoload'] === true)
		{
			$this->composer->dumpAutoloads();
		}
	}

	/**
	 * Build the class with the given name.
	 *
	 * @param  string $name
	 * @return string
	 */
	protected function buildClass($name)
	{
		$stub = $this->files->get($this->getStub());

		// foo.bar = App\Foo
		$stub = str_replace('{{namespace}}', $this->getNamespace($name), $stub);

		// App\
		$stub = str_replace('{{rootNamespace}}', $this->getAppNamespace(), $stub);

		// foo.bar = bar
		$stub = str_replace('{{class}}', $this->getClassName(), $stub);

		$url = $this->getUrl();

		// /foo/bar
		$stub = str_replace('{{url}}', $this->getUrl(), $stub);

		// posts
		$stub = str_replace('{{collection}}', $this->getCollectionName($url), $stub);

		// Post
		$stub = str_replace('{{model}}', $this->getModelName($url), $stub);

		// post
		$stub = str_replace('{{resource}}', $this->getResourceName($url), $stub);

		// Posts
		$stub = str_replace('{{collectionUpper}}', ucwords($this->getCollectionName($url)), $stub);

		// Posts
		$stub = str_replace('{{path}}', ucwords($this->getPath($url)), $stub);

		// posts || posts.comments
		$stub = str_replace('{{view}}', $this->getViewPath($url), $stub);

		// posts
		$stub = str_replace('{{table}}', $this->getTableName($url), $stub);

		return $stub;
	}

	/**
	 * Get the destination class path.
	 *
	 * @param  string $name
	 * @return string
	 */
	protected function getPath($name)
	{
		$name = $this->getFileName();

		$withName = boolval($this->option('view-name'));

		$path = $this->settings['path'] . $this->getArgumentPath($withName) . $name;

		return $path;
	}

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		$key = $this->getOptionStubKey();

		// get the stub path
		$stub = config('generators.' . $key);

		if (is_null($stub))
		{
			$this->error('The stub does not exist in the config file - "' . $key . '"');
			exit;
		}

		return $stub;
	}

	/**
	 * Get the key where the stub is located
	 *
	 * @return string
	 */
	protected function getOptionStubKey()
	{
		$plain = $this->option('plain');
		$stub = $this->option('stub') . ($plain ? '_plain' : '') . '_stub';

		// if no stub, we assume its the same as the type
		if (is_null($this->option('stub')))
		{
			$stub = $this->option('type') . ($plain ? '_plain' : '') . '_stub';
		}

		return $stub;
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array_merge([
			['type', null, InputOption::VALUE_OPTIONAL, 'The type of file: model, view, controller, migration, seed', 'view'],
			['view-name', null, InputOption::VALUE_NONE, 'If you want a custom name for the view files'],
		], parent::getOptions());
	}
}