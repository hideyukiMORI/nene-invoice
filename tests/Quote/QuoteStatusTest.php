<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote;

use NeneInvoice\Quote\QuoteStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuoteStatusTest extends TestCase
{
    /**
     * Exhaustive 5×5 transition matrix. Only draft→sent and
     * sent→{accepted,rejected,expired} are legal; self-transitions and every
     * move out of a terminal state (accepted/rejected/expired) are illegal.
     */
    #[DataProvider('transitionMatrix')]
    public function test_can_transition_to(QuoteStatus $from, QuoteStatus $to, bool $expected): void
    {
        self::assertSame($expected, $from->canTransitionTo($to));
    }

    /** @return iterable<string, array{QuoteStatus, QuoteStatus, bool}> */
    public static function transitionMatrix(): iterable
    {
        $legal = ['Draft->Sent', 'Sent->Accepted', 'Sent->Rejected', 'Sent->Expired'];

        foreach (QuoteStatus::cases() as $from) {
            foreach (QuoteStatus::cases() as $to) {
                $key = $from->name . '->' . $to->name;
                yield $key => [$from, $to, in_array($key, $legal, true)];
            }
        }
    }

    public function test_terminal_states_have_no_outgoing_transitions(): void
    {
        foreach ([QuoteStatus::Accepted, QuoteStatus::Rejected, QuoteStatus::Expired] as $terminal) {
            foreach (QuoteStatus::cases() as $target) {
                self::assertFalse(
                    $terminal->canTransitionTo($target),
                    sprintf('%s must be terminal but allowed → %s', $terminal->name, $target->name),
                );
            }
        }
    }

    public function test_no_state_can_transition_to_itself(): void
    {
        foreach (QuoteStatus::cases() as $state) {
            self::assertFalse($state->canTransitionTo($state), $state->name . ' allowed a self-transition');
        }
    }
}
