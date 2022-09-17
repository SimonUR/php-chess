<?php

namespace Chess;

use Chess\Grandmaster;
use Chess\FEN\BoardToStr;
use Chess\FEN\ShortStrToPgn;
use Chess\FEN\StrToBoard;
use Chess\PGN\AN\Castle;
use Chess\PGN\AN\Color;
use Chess\UciEngine\Stockfish;
use Chess\ML\Supervised\Regression\GeometricSumPredictor;
use Chess\Variant\Chess960\Board as Chess960Board;
use Chess\Variant\Classical\Board as ClassicalBoard;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

/**
 * Game
 *
 * Game is the main component of the PHP Chess Server. It is a wrapper for the
 * Chess\Board object to play chess online but it is also used on command line
 * (CLI) apps as well as in APIs.
 *
 * @author Jordi Bassagañas
 * @license GPL
 * @link https://github.com/chesslablab/chess-server
 */
class Game
{
    const VARIANT_960           = '960';
    const VARIANT_CLASSICAL     = 'classical';

    const MODE_ANALYSIS         = 'analysis';
    const MODE_GM               = 'gm';
    const MODE_FEN              = 'fen';
    const MODE_PGN              = 'pgn';
    const MODE_PLAY             = 'play';
    const MODE_RUBIX            = 'rubix';
    const MODE_STOCKFISH        = 'stockfish';

    const MODEL_FOLDER          = __DIR__.'/../model/';

    const MODEL_FILE            = 'regression/checkmate_king_and_rook_vs_king.model';

    /**
     * Chess board.
     *
     * @var \Chess\Variant\Classical\Board
     */
    private ClassicalBoard $board;

    /**
     * Variant.
     *
     * @var string
     */
    private string $variant;

    /**
     * Mode.
     *
     * @var string
     */
    private string $mode;

    /**
     * Grandmaster.
     *
     * @var Grandmaster
     */
    private null|Grandmaster $gm;

    public function __construct(
        string $variant = null,
        string $mode = null,
        null|Grandmaster $gm = null
    ) {
        $this->variant = $variant;
        $this->mode = $mode;
        $this->gm = $gm;

        if ($this->variant === self::VARIANT_CLASSICAL) {
            $this->board = new ClassicalBoard();
        } elseif ($this->variant === self::VARIANT_960) {
            $this->board = new Chess960Board();
        }
    }

    /**
     * Returns the Chess\Board object.
     *
     * @return \Chess\Variant\Classical\Board
     */
    public function getBoard(): ClassicalBoard
    {
        return $this->board;
    }

    /**
     * Returns the game mode.
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Sets the Chess\Board object.
     *
     * @param \Chess\Variant\Classical\Board
     * @return \Chess\Game
     */
    public function setBoard(Board $board): Game
    {
        $this->board = $board;

        return $this;
    }

    /**
     * Returns the state of the board.
     *
     * @return object
     */
    public function state(): object
    {
        $history = $this->board->getHistory();
        $end = end($history);

        return (object) [
            'turn' => $this->board->getTurn(),
            'pgn' => $end ? $end->move->pgn : null,
            'castlingAbility' => $this->board->getCastlingAbility(),
            'movetext' => $this->board->getMovetext(),
            'fen' => $this->board->toFen(),
            'isCheck' => $this->board->isCheck(),
            'isMate' => $this->board->isMate(),
            'isStalemate' => $this->board->isStalemate(),
            'mode' => $this->getMode(),
        ];
    }

    /**
     * Makes a move.
     *
     * @param string $color
     * @param string $pgn
     * @return bool true if the move can be made; otherwise false
     */
    public function play(string $color, string $pgn): bool
    {
        return $this->board->play($color, $pgn);
    }

    /**
     * Returns a computer generated response to the current position.
     *
     * @param array $options
     * @param array $params
     * @return mixed object|null
     */
    public function ai(array $options = [], array $params = []): ?object
    {
        if ($this->mode === Game::MODE_GM) {
            return $this->gm->move($this);
        } else if ($this->mode === Game::MODE_RUBIX) {
            if ($this->gm) {
                if ($move = $this->gm->move($this)) {
                    return $move;
                }
            }
            $estimator = PersistentModel::load(
                new Filesystem(self::MODEL_FOLDER.self::MODEL_FILE)
            );
            $move = (new GeometricSumPredictor(
                $this->board,
                $estimator
            ))->predict();
            return (object) [
                'move' => $move,
            ];
        }

        if ($this->gm) {
            if ($move = $this->gm->move($this)) {
                return $move;
            }
        }

        $stockfish = (new Stockfish($this->board))
            ->setOptions($options)
            ->setParams($params);

        $fromFen = $this->board->toFen();
        $toFen = $stockfish->shortFen($fromFen);
        $pgn = (new ShortStrToPgn($fromFen, $toFen))->create();

        return (object) [
            'move' => current($pgn),
        ];
    }

    /**
     * Loads a FEN string allowing to continue a chess game.
     *
     * @param string
     */
    public function loadFen(string $string): void
    {
        $this->board = (new StrToBoard($string))->create();
    }

    /**
     * Loads a PGN movetext allowing to continue a chess game.
     *
     * @param string
     */
    public function loadPgn(string $movetext): void
    {
        $this->board = (new Player($movetext))->play()->getBoard();
    }

    /**
     * Makes a move in short FEN format. Only the piece placement and the side
     * to move are required.
     *
     * @param string $toShortFen
     * @return mixed bool|string
     */
    public function playFen(string $toShortFen): bool|string
    {
        $fromFen = (new BoardToStr($this->board))->create();

        $fromPiecePlacement = explode(' ', $fromFen)[0];
        $toPiecePlacement = explode(' ', $toShortFen)[0];
        $fromRanks = explode('/', $fromPiecePlacement);
        $toRanks = explode('/', $toPiecePlacement);

        if (
          'K2R' === substr($fromRanks[7], -3) &&
          'KR' === substr($toRanks[7], -2) &&
          $this->board->play(Color::W, Castle::SHORT)
        ) {
            return true;
        } elseif (
          'R3K' === substr($fromRanks[7], 0, 3) &&
          'R1K' === substr($toRanks[7], 0, 3) &&
          $this->board->play(Color::W, Castle::LONG)
        ) {
            return true;
        } elseif (
          'k2r' === substr($fromRanks[0], -3) &&
          'kr' === substr($toRanks[0], -2) &&
          $this->board->play(Color::B, Castle::SHORT)
        ) {
            return true;
        } elseif (
          'r3k' === substr($fromRanks[0], 0, 3) &&
          'r1k' === substr($toRanks[0], 0, 3) &&
          $this->board->play(Color::B, Castle::LONG)
        ) {
            return true;
        }

        $pgn = (new ShortStrToPgn($fromFen, $toShortFen))->create();
        $color = key($pgn);
        $result = current($pgn);

        if ($result) {
            $clone = unserialize(serialize($this->board));
            $clone->play($color, $result);
            $clone->isMate() ? $check = '#' : ($clone->isCheck() ? $check = '+' : $check = '');
            return $this->board->play($color, $result.$check);
        }

        return false;
    }
}
