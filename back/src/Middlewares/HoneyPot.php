<?php

declare(strict_types=1);

namespace App\Middlewares;

use Lumynus\Framework\AbstractMiddleware;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class HoneyPot extends AbstractMiddleware
{
    private const MEMORY_KEY = 'honeypot_blocked_ips';
    private const BLOCK_SECONDS = 30;

    public function handle(Request $req, Response $res)
    {
        $ip = $req->server()['REMOTE_ADDR'] ?? '127.0.0.1';
        $blocked = $this->getCleanedBlockedList();

        if (isset($blocked[$ip])) {
            $this->saveBlockedList($blocked);
            return $this->respondToManyAttempts($res);
        }

        if ($this->shouldBlock($req)) {
            $blocked[$ip] = time() + self::BLOCK_SECONDS;
            $this->saveBlockedList($blocked);
            return $this->respondAccessBlocked($res);
        }

        $this->saveBlockedList($blocked);
    }

    private function getCleanedBlockedList(): array
    {
        $blocked = $this->memory()->read(self::MEMORY_KEY) ?? [];
        $now = time();

        foreach ($blocked as $blockedIp => $expiresAt) {
            if ($expiresAt <= $now) {
                unset($blocked[$blockedIp]);
            }
        }

        return $blocked;
    }

    private function saveBlockedList(array $blocked): void
    {
        $this->memory()->write(self::MEMORY_KEY, $blocked);
    }

    private function shouldBlock(Request $req): bool
    {
        return $this->hasHoneypotTriggered($req) || $this->isBot($req);
    }

    private function hasHoneypotTriggered(Request $req): bool
    {
        return !empty($req->body('confirmaLogin')) || !empty($req->body('confirmaRecuperacao'));
    }

    private function isBot(Request $req): bool
    {
        $headers = $req->getHeaders();
        $userAgent = (string) ($headers['user-agent'] ?? '');

        // // Ignora verificações se for Postman ou Insomnia (ferramentas de dev)
        // if (str_contains($userAgent, 'Postman') || str_contains($userAgent, 'Insomnia')) {
        //     return false;
        // }

        return empty($userAgent) ||
            str_contains($userAgent, 'curl') ||
            str_contains($userAgent, 'python') ||
            str_contains($userAgent, 'Headless');
    }

    private function respondToManyAttempts(Response $res): Response
    {
        return $res->status(429)->json([
            'success' => false,
            'message' => 'Muitas tentativas. Aguarde alguns segundos.'
        ]);
    }

    private function respondAccessBlocked(Response $res): Response
    {
        return $res->status(403)->json([
            'success' => false,
            'message' => 'Acesso temporariamente bloqueado.'
        ]);
    }
}
