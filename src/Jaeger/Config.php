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

namespace Jaeger;

use const Jaeger\Constants\PROPAGATOR_JAEGER;
use const Jaeger\Constants\PROPAGATOR_ZIPKIN;
use Jaeger\Propagator\JaegerPropagator;
use Jaeger\Propagator\ZipkinPropagator;
use Jaeger\Reporter\RemoteReporter;
use Jaeger\Reporter\Reporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\Sampler;
use Jaeger\Transport\Transport;
use Jaeger\Transport\TransportUdp;
use OpenTracing\NoopTracer;
use OpenTracing\Span as OpenTracingSpan;
use OpenTracing\Tracer;
use RuntimeException;

class Config
{
    /**
     * @var Transport|null
     */
    private $transport;

    /**
     * @var Reporter|null
     */
    private $reporter;

    /**
     * @var Sampler|null
     */
    private $sampler;

    /**
     * @var ScopeManager|null
     */
    private $scopeManager;

    private $gen128bit = false;

    /**
     * @var array|null
     */
    public static $tracer;

    /**
     * @var OpenTracingSpan|null
     */
    public static $span;

    /**
     * @var self|null
     */
    public static $instance;

    public static $disabled = false;

    public static $propagator = PROPAGATOR_JAEGER;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance(): ?Config
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * init tracer.
     *
     * @throws RuntimeException
     */
    public function initTracer(string $serviceName, string $agentHostPort = ''): Tracer
    {
        if (self::$disabled) {
            return new NoopTracer();
        }

        if ('' == $serviceName) {
            throw new RuntimeException('serviceName require');
        }

        if (isset(self::$tracer[$serviceName]) && !empty(self::$tracer[$serviceName])) {
            return self::$tracer[$serviceName];
        }

        if (null == $this->transport) {
            $this->transport = new TransportUdp($agentHostPort);
        }

        if (null == $this->reporter) {
            $this->reporter = new RemoteReporter($this->transport);
        }

        if (null == $this->sampler) {
            $this->sampler = new ConstSampler(true);
        }

        if (null == $this->scopeManager) {
            $this->scopeManager = new ScopeManager();
        }

        $tracer = new Jaeger($serviceName, $this->reporter, $this->sampler, $this->scopeManager);

        if (true === $this->gen128bit) {
            $tracer->gen128bit();
        }

        if (PROPAGATOR_ZIPKIN === self::$propagator) {
            $tracer->setPropagator(new ZipkinPropagator());
        } else {
            $tracer->setPropagator(new JaegerPropagator());
        }

        self::$tracer[$serviceName] = $tracer;

        return $tracer;
    }

    public function setDisabled(bool $disabled): Config
    {
        self::$disabled = $disabled;

        return $this;
    }

    public function setTransport(Transport $transport): Config
    {
        $this->transport = $transport;

        return $this;
    }

    public function setReporter(Reporter $reporter): Config
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function setSampler(Sampler $sampler): Config
    {
        $this->sampler = $sampler;

        return $this;
    }

    public function gen128bit(): Config
    {
        $this->gen128bit = true;

        return $this;
    }

    public function flush(): bool
    {
        if (count(self::$tracer) > 0) {
            foreach (self::$tracer as $tracer) {
                $tracer->reportSpan();
            }
            $this->reporter->close();
        }

        return true;
    }
}
