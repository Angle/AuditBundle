<?php

namespace Angle\AuditBundle\Extension;

use Symfony\Component\Console\Output\ConsoleOutput;

class ConsoleWithBufferOutput extends ConsoleOutput
{
    private string $buffer = '';

    /**
     * Empties buffer and returns its content.
     */
    public function fetch(): string
    {
        $content = $this->buffer;
        $this->buffer = '';

        return $content;
    }

    /**
     * @return void
     */
    protected function doWrite(string $message, bool $newline)
    {
        // flush to the upstream
        parent::doWrite($message, $newline);

        // but also store it within our buffer to be fetched later
        $this->buffer .= $message;

        if ($newline) {
            $this->buffer .= \PHP_EOL;
        }
    }
}