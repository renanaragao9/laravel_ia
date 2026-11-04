<?php

use App\Http\Middleware\EnsureMcpToken;
use App\Mcp\Servers\BichoServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('bicho', BichoServer::class);

Mcp::web('/mcp/bicho', BichoServer::class)->middleware(EnsureMcpToken::class);
