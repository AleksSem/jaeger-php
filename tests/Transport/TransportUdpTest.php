<?php
/*
 * Copyright (c) 2019, The Jaeger Authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

namespace tests\Transport;

use Jaeger\Jaeger;
use Jaeger\Reporter\RemoteReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\ScopeManager;
use Jaeger\Sender\Sender;
use Jaeger\Transport\TransportUdp;
use PHPUnit\Framework\TestCase;

class TransportUdpTest extends TestCase
{
    /**
     * @var TransportUdp|null
     */
    public $tran;

    /**
     * @var Jaeger|null
     */
    public $tracer;

    public function setUp(): void
    {
        $senderMock = $this->createMock(Sender::class);
        $senderMock->method('emitBatch')->willReturn(true);

        $this->tran = new TransportUdp('localhost:6831', 0, $senderMock);

        $reporter = new RemoteReporter($this->tran);
        $sampler = new ConstSampler();
        $scopeManager = new ScopeManager();

        $this->tracer = new Jaeger('jaeger', $reporter, $sampler, $scopeManager);
    }

    public function testBuildAndCalcSizeOfProcessThrift(): void
    {
        $span = $this->tracer->startSpan('BuildAndCalcSizeOfProcessThrift');
        $span->finish();
        $this->tran->buildAndCalcSizeOfProcessThrift($this->tracer);
        static::assertEquals(95, $this->tran->procesSize);
    }
}
