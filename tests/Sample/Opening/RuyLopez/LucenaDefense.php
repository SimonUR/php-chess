<?php

namespace PGNChess\Tests\Sample\Opening\RuyLopez;

use PGNChess\PGN\Convert;
use PGNChess\PGN\Symbol;
use PGNChess\Tests\Sample\AbstractOpening;

class LucenaDefense extends AbstractOpening
{
    public function play()
    {
        $this->board->play(Convert::toStdObj(Symbol::WHITE, 'e4'));
        $this->board->play(Convert::toStdObj(Symbol::BLACK, 'e5'));
        $this->board->play(Convert::toStdObj(Symbol::WHITE, 'Nf3'));
        $this->board->play(Convert::toStdObj(Symbol::BLACK, 'Nc6'));
        $this->board->play(Convert::toStdObj(Symbol::WHITE, 'Bb5'));
        $this->board->play(Convert::toStdObj(Symbol::BLACK, 'Be7'));

        return $this->board;
    }
}