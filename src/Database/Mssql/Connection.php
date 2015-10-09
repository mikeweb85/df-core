<?php
namespace DreamFactory\Core\Database\Mssql;

/**
 * Connection represents a connection to a Microsoft SQL Server database.
 */
class Connection extends \DreamFactory\Core\Database\Connection
{
    public $pdoClass = 'DreamFactory\Core\Database\Mssql\PdoAdapter';

    public static function checkRequirements($driver, $throw_exception = true)
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $driver = 'sqlsrv';
            $extension = 'sqlsrv';
        } else {
            $driver = 'dblib';
            $extension = 'mssql';
        }

        if (!extension_loaded($extension)) {
            if ($throw_exception) {
                throw new \Exception("Required extension or module '$extension' is not installed or loaded.");
            } else {
                return false;
            }
        }

        return parent::checkRequirements($driver, $throw_exception);
    }

    public static function getDriverLabel()
    {
        return 'SQL Server';
    }

    public static function getSampleDsn()
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            // http://php.net/manual/en/ref.pdo-sqlsrv.connection.php
            return 'sqlsrv:Server=localhost,1433;Database=db';
        }

        // http://php.net/manual/en/ref.pdo-dblib.connection.php
        return 'dblib:host=localhost:1433;dbname=database';
    }

    public function __construct($dsn = '', $username = '', $password = '')
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            // MS SQL Server on Windows
            $this->pdoClass = 'DreamFactory\Core\Database\Mssql\SqlsrvPdoAdapter';
        }

        parent::__construct($dsn, $username, $password);
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}