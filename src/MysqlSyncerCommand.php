<?php

namespace Jezzis\MysqlSyncer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Jezzis\MysqlSyncer\Client\ClientInterface;
use Jezzis\MysqlSyncer\Parser\Parser;

class MysqlSyncerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:sync
                             {file : The filename of the sql file, without .sql extension}
                             {--drop : allow drop redundant columns}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize database structure';

    protected $file, $permitDrop = false;

    protected $sqlPath = './';

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var ClientInterface
     */
    protected $client;

    protected function init($driver = 'mysql')
    {
        $this->sqlPath = Config::get('msyncer.sql_path', './');

        $this->file = $this->argument('file');

        // client
        $driver = Config::get('driver') ?: 'mysql';
        $className = '\\Jezzis\\MysqlSyncer\\Client\\' . ucfirst($driver) . 'Client';
        $this->client = new $className;

        // parser
        $this->permitDrop = $this->option('drop');
        $this->parser = new Parser($this->client, $this->permitDrop);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    function handle()
    {
        $this->init();

        $this->resolveFile();

        $this->start();
    }

    protected function resolveFile()
    {
        $file = rtrim($this->sqlPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->file . '.sql';
        while (!file_exists($file)) {
            $file = $this->ask('cannot find file [' . $file . '], please retype');
            $file = rtrim($this->sqlPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file . '.sql';
        }
        $this->file = $file;
    }

    public function start()
    {
        $sourceSql = file_get_contents($this->file);

        $this->parser->parse($sourceSql);

        $messages = $this->parser->getMsgs();
        foreach ($messages as $message) {
            list($msg, $level, $style) = $message;
            $this->line($msg, $style, $level);
        }

        $execSqlList = $this->parser->getExecSqlList();
        if (empty($execSqlList)) {
            $this->info("\n nothing to do.");
            return ;
        }

        $this->info("\n- execute sql: \n\n" . implode("\n\n", $execSqlList));
        $warning = 'continue: y|n ? ';
        if ($this->permitDrop) {
            $warning .= '(with drop option enabled, please be careful!)';
        }
        $confirm = $this->ask($warning);
        if (strtolower($confirm) == 'y') {
            $this->client->execSqlList($execSqlList);
        } else {
            $this->info("\n do nothing.");
        }
    }
}
