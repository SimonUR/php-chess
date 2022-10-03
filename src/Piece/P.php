<?php

namespace Chess\Piece;

use Chess\Exception\UnknownNotationException;
use Chess\Piece\AbstractPiece;
use Chess\Variant\Classical\PGN\AN\Color;
use Chess\Variant\Classical\PGN\AN\Piece;

/**
 * Pawn.
 *
 * @author Jordi Bassagañas
 * @license GPL
 */
class P extends AbstractPiece
{
    /**
     * @var string
     */
    private string $file;

    /**
     * @var object
     */
    private object $ranks;

    /**
     * @var array
     */
    private array $captureSqs;

    /**
     * @var string
     */
    private string $enPassantSq = '';

    /**
     * Constructor.
     *
     * @param string $color
     * @param string $sq
     * @param array $size
     */
    public function __construct(string $color, string $sq, array $size)
    {
        parent::__construct($color, $sq, $size, Piece::P);

        $this->file = $this->sq[0];

        if ($this->color === Color::W) {
            $this->ranks = (object) [
                'start' => 2,
                'next' => intval(ltrim($this->sq, $this->sq[0])) + 1,
                'end' => $this->size['ranks'],
            ];
        } elseif ($this->color === Color::B) {
            $this->ranks = (object) [
                'start' => $this->size['ranks'] - 1,
                'next' => intval(ltrim($this->sq, $this->sq[0])) - 1,
                'end' => 1,
            ];
        }

        $this->captureSqs = [];

        $this->mobility = [];

        $this->mobility();
    }

    /**
     * Calculates the piece's mobility.
     *
     * @return \Chess\Piece\AbstractPiece
     */
    protected function mobility(): AbstractPiece
    {
        // next rank
        try {
            if ($this->isValidSq($this->file.$this->ranks->next)) {
                $this->mobility[] = $this->file . $this->ranks->next;
            }
        } catch (UnknownNotationException $e) {

        }

        // two square advance
        if (intval(ltrim($this->sq, $this->sq[0])) === 2 && $this->ranks->start == 2) {
            $this->mobility[] = $this->file . ($this->ranks->start + 2);
        } elseif (
            intval(ltrim($this->sq, $this->sq[0])) === $this->size['ranks'] - 1 &&
            $this->ranks->start == $this->size['ranks'] - 1
        ) {
            $this->mobility[] = $this->file . ($this->ranks->start - 2);
        }

        // capture square
        try {
            $file = chr(ord($this->file) - 1);
            if ($this->isValidSq($file.$this->ranks->next)) {
                $this->captureSqs[] = $file . $this->ranks->next;
            }
        } catch (UnknownNotationException $e) {

        }

        // capture square
        try {
            $file = chr(ord($this->file) + 1);
            if ($this->isValidSq($file.$this->ranks->next)) {
                $this->captureSqs[] = $file . $this->ranks->next;
            }
        } catch (UnknownNotationException $e) {

        }

        return $this;
    }

    /**
     * Returns the piece's legal moves.
     *
     * @return array
     */
    public function sqs(): array
    {
        $sqs = [];
        $history = $this->board->getHistory();
        $end = end($history);

        // mobility squares
        foreach ($this->mobility as $sq) {
            if (in_array($sq, $this->board->getSqEval()->free)) {
                $sqs[] = $sq;
            } else {
                break;
            }
        }

        // capture squares
        foreach ($this->captureSqs as $sq) {
            if (in_array($sq, $this->board->getSqEval()->used->{$this->oppColor()})) {
                $sqs[] = $sq;
            }
        }

        // en passant squares
        if ($end && $end->move->id === Piece::P && $end->move->color === $this->oppColor()) {
            if ($this->color === Color::W) {
                if (intval(ltrim($this->sq, $this->sq[0])) === $this->size['ranks'] - 3) {
                    $captureSq = $end->move->sq->next[0].($end->move->sq->next[1]+1);
                    if (in_array($captureSq, $this->captureSqs)) {
                        $this->enPassantSq = $end->move->sq->next;
                        $sqs[] = $captureSq;
                    }
                }
            } elseif ($this->color === Color::B) {
                if (intval(ltrim($this->sq, $this->sq[0])) === 4) {
                    $captureSq = $end->move->sq->next[0].($end->move->sq->next[1]-1);
                    if (in_array($captureSq, $this->captureSqs)) {
                        $this->enPassantSq = $end->move->sq->next;
                        $sqs[] = $captureSq;
                    }
                }
            }
        }

        return $sqs;
    }

    /**
     * Returns the squares defended by the piece.
     *
     * @return mixed array|null
     */
    public function defendedSqs(): ?array
    {
        $sqs = [];
        foreach($this->captureSqs as $sq) {
            if (in_array($sq, $this->board->getSqEval()->used->{$this->getColor()})) {
                $sqs[] = $sq;
            }
        }

        return $sqs;
    }

    /**
     * Gets the pawn's file.
     *
     * @return string
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Gets the pawn's rank info.
     *
     * @return object
     */
    public function getRanks(): object
    {
        return $this->ranks;
    }

    /**
     * Gets the capture squares.
     *
     * @return array
     */
    public function getCaptureSqs(): array
    {
        return $this->captureSqs;
    }

    /**
     * Gets the en passant square.
     *
     * @return string
     */
    public function getEnPassantSq(): ?string
    {
        return $this->enPassantSq;
    }

    /**
     * Checks whether the pawn is promoted.
     *
     * @return boolean
     */
    public function isPromoted(): bool
    {
        return isset($this->move->newId) &&
            (int)$this->getMove()->sq->next[1] === $this->ranks->end;
    }
}