<?php declare(strict_types=1);


namespace Swoft\Rpc\Client;


use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\Concern\PrototypeTrait;
use Swoft\Connection\Pool\AbstractConnection;
use Swoft\Rpc\Client\Contract\ConnectionInterface;
use Swoft\Rpc\Client\Contract\ProviderInterface;
use Swoft\Rpc\Client\Exception\RpcClientException;
use Swoft\Rpc\Contract\PacketInterface;
use Swoft\Stdlib\Helper\JsonHelper;
use Swoole\Coroutine\Client;
use Swoft\Rpc\Client\Client as RpcClient;

/**
 * Class Connection
 *
 * @since 2.0
 *
 * @Bean(scope=Bean::PROTOTYPE)
 */
class Connection extends AbstractConnection implements ConnectionInterface
{
    use PrototypeTrait;

    /**
     * @var Client
     */
    protected $connection;

    /**
     * @var RpcClient
     */
    protected $client;

    /**
     * @param \Swoft\Rpc\Client\Client $client
     * @param Pool                     $pool
     *
     * @return Connection
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public static function new(RpcClient $client, Pool $pool): Connection
    {
        $instance = self::__instance();

        $instance->client = $client;
        $instance->pool   = $pool;

        return $instance;
    }

    /**
     * @throws RpcClientException
     */
    public function create(): void
    {
        $connection = new Client(SWOOLE_SOCK_TCP);

        [$host, $port] = $this->getHostPort();
        $setting = $this->client->getSetting();

        if (!empty($setting)) {
            $connection->set($setting);
        }

        if (!$connection->connect($host, $port)) {
            throw new RpcClientException(
                sprintf('Connect failed. host=%s port=%d', $host, $port)
            );
        }

        $this->connection = $connection;
    }

    /**
     * @return bool
     */
    public function reconnect(): bool
    {
        return false;
    }

    /**
     * @return PacketInterface
     * @throws RpcClientException
     */
    public function getPacket(): PacketInterface
    {
        return $this->client->getPacket();
    }

    /**
     * @return \Swoft\Rpc\Client\Client
     */
    public function getClient(): \Swoft\Rpc\Client\Client
    {
        return $this->client;
    }

    /**
     * @param string $data
     *
     * @return bool
     */
    public function send(string $data): bool
    {
        return (bool)$this->connection->send($data);
    }

    /**
     * @return string
     */
    public function recv(): string
    {
        return $this->connection->recv((float)-1);
    }

    /**
     * @return int
     */
    public function getLastTime(): int
    {
        return time();
    }

    /**
     * @return array
     * @throws RpcClientException
     */
    private function getHostPort(): array
    {
        $provider = $this->client->getProvider();
        if (empty($provider) || !$provider instanceof ProviderInterface || empty($provider->getList())) {
            return [$this->client->getHost(), $this->client->getPort()];
        }

        $list = $provider->getList();
        if (!is_array($list)) {
            throw new RpcClientException(
                \sprintf('Provider(%s) return format is error!', JsonHelper::encode($list))
            );
        }
        $randKey  = array_rand($list, 1);
        $hostPort = \explode(':', $list[$randKey]);

        if (\count($hostPort) < 2) {
            throw new RpcClientException(
                \sprintf('Provider(%s) return format is error!', JsonHelper::encode($hostPort))
            );
        }

        [$host, $port] = $hostPort;

        return [$host, $port];
    }
}