<?php

namespace Pterodactyl\Services\ServerImporter;

use Illuminate\Support\Facades\Log;

class TestConnectionService
{
    private const CONNECT_TIMEOUT = 10;

    public function __construct()
    {
    }

    /**
     * Test an SSH connection to a remote server.
     */
    public function testSsh(string $host, int $port, string $username, ?string $password = null, ?string $privateKey = null, ?string $passphrase = null): array
    {
        $result = [
            'success' => false,
            'host' => $host,
            'port' => $port,
            'protocol' => 'ssh',
            'error' => null,
            'response_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            // Try using ssh2 extension if available
            if (function_exists('ssh2_connect')) {
                $connection = @ssh2_connect($host, $port, ['timeout' => self::CONNECT_TIMEOUT]);

                if (!$connection) {
                    $result['error'] = sprintf('Could not connect to %s:%d', $host, $port);
                    return $result;
                }

                $authResult = false;
                if ($privateKey) {
                    $authResult = @ssh2_auth_pubkey_file($connection, $username, '', $privateKey, $passphrase);
                } elseif ($password) {
                    $authResult = @ssh2_auth_password($connection, $username, $password);
                }

                if ($authResult) {
                    $result['success'] = true;
                } else {
                    $result['error'] = 'Authentication failed';
                }
            } else {
                // Fallback: try connecting with fsockopen
                $socket = @fsocknew('tcp', $host, $port, $errno, $errstr, self::CONNECT_TIMEOUT);

                if ($socket) {
                    fclose($socket);
                    $result['success'] = true;
                    $result['error'] = null;
                } else {
                    $result['error'] = sprintf('Connection failed: %s (%d)', $errstr, $errno);
                }
            }

            $result['response_time'] = round((microtime(true) - $startTime) * 1000);

            return $result;
        } catch (\Exception $exception) {
            $result['error'] = $exception->getMessage();
            $result['response_time'] = round((microtime(true) - $startTime) * 1000);

            return $result;
        }
    }

    /**
     * Test an FTP connection to a remote server.
     */
    public function testFtp(string $host, int $port, string $username, string $password): array
    {
        $result = [
            'success' => false,
            'host' => $host,
            'port' => $port,
            'protocol' => 'ftp',
            'error' => null,
            'response_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            $connection = @ftp_connect($host, $port, self::CONNECT_TIMEOUT);

            if (!$connection) {
                $result['error'] = sprintf('Could not connect to %s:%d', $host, $port);
                $result['response_time'] = round((microtime(true) - $startTime) * 1000);

                return $result;
            }

            $loginResult = @ftp_login($connection, $username, $password);

            if ($loginResult) {
                $result['success'] = true;
                $result['server_type'] = ftp_systype($connection);
            } else {
                $result['error'] = 'FTP authentication failed';
            }

            ftp_close($connection);
            $result['response_time'] = round((microtime(true) - $startTime) * 1000);

            return $result;
        } catch (\Exception $exception) {
            $result['error'] = $exception->getMessage();
            $result['response_time'] = round((microtime(true) - $startTime) * 1000);

            return $result;
        }
    }

    /**
     * Test an SFTP connection to a remote server.
     */
    public function testSftp(string $host, int $port, string $username, ?string $password = null, ?string $privateKey = null): array
    {
        $result = [
            'success' => false,
            'host' => $host,
            'port' => $port,
            'protocol' => 'sftp',
            'error' => null,
            'response_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            if (!function_exists('ssh2_connect')) {
                $result['error'] = 'SSH2 extension is not available';

                return $result;
            }

            $connection = @ssh2_connect($host, $port, ['timeout' => self::CONNECT_TIMEOUT]);

            if (!$connection) {
                $result['error'] = sprintf('Could not connect to %s:%d', $host, $port);
                $result['response_time'] = round((microtime(true) - $startTime) * 1000);

                return $result;
            }

            $authResult = false;
            if ($privateKey) {
                $authResult = @ssh2_auth_pubkey_file($connection, $username, '', $privateKey);
            } elseif ($password) {
                $authResult = @ssh2_auth_password($connection, $username, $password);
            }

            if ($authResult) {
                $sftp = @ssh2_sftp($connection);
                if ($sftp) {
                    $result['success'] = true;
                } else {
                    $result['error'] = 'SFTP session could not be established';
                }
            } else {
                $result['error'] = 'SFTP authentication failed';
            }

            $result['response_time'] = round((microtime(true) - $startTime) * 1000);

            return $result;
        } catch (\Exception $exception) {
            $result['error'] = $exception->getMessage();
            $result['response_time'] = round((microtime(true) - $startTime) * 1000);

            return $result;
        }
    }

    /**
     * Test a generic TCP connection.
     */
    public function testTcp(string $host, int $port): array
    {
        $result = [
            'success' => false,
            'host' => $host,
            'port' => $port,
            'protocol' => 'tcp',
            'error' => null,
            'response_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            $socket = @fsocknew('tcp', $host, $port, $errno, $errstr, self::CONNECT_TIMEOUT);

            if ($socket) {
                fclose($socket);
                $result['success'] = true;
            } else {
                $result['error'] = sprintf('Connection failed: %s (%d)', $errstr, $errno);
            }

            $result['response_time'] = round((microtime(true) - $startTime) * 1000);

            return $result;
        } catch (\Exception $exception) {
            $result['error'] = $exception->getMessage();
            $result['response_time'] = round((microtime(true) - $startTime) * 1000);

            return $result;
        }
    }
}
