<?php

require_once __DIR__ . '/../src/Controller/GameController.php';

class GameControllerTest
{
    public function run(): void
    {
        $this->testWordValidationRequiresSixLetters();
        $this->testResolveOutcomeDetectsLossAfterMaxAttempts();
        $this->testResolveOutcomeDetectsWin();
        $this->testEvaluateGuessPatternHandlesDuplicateLetters();
    }

    private function assertTrue(bool $condition, string $message = 'Expected condition to be true'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    private function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $errorMessage = $message !== '' ? $message : sprintf('Failed asserting that %s matches expected %s', var_export($actual, true), var_export($expected, true));
            throw new RuntimeException($errorMessage);
        }
    }

    private function testWordValidationRequiresSixLetters(): void
    {
        $this->assertTrue(GameController::isValidWord('ABCDEF'), 'Six letter word should be valid');
        $this->assertTrue(!GameController::isValidWord('ABCDE'), 'Shorter word should be invalid');
        $this->assertTrue(!GameController::isValidWord('ABCDEFG'), 'Longer word should be invalid');
        $this->assertTrue(!GameController::isValidWord('ABC12F'), 'Word with digits should be invalid');
    }

    private function testResolveOutcomeDetectsLossAfterMaxAttempts(): void
    {
        $status = GameController::determineStatusFromGuess(false, 8, 8);
        $this->assertEquals('lost', $status, 'Should mark game as lost at attempt limit');
    }

    private function testResolveOutcomeDetectsWin(): void
    {
        $status = GameController::determineStatusFromGuess(true, 3, 8);
        $this->assertEquals('won', $status, 'Should mark game as won when guess correct');
    }

    private function testEvaluateGuessPatternHandlesDuplicateLetters(): void
    {
        $pattern = GameController::evaluateGuessPattern('BALLET', 'LALLEY');
        $this->assertEquals(6, count($pattern), 'Pattern should contain six entries');
        $this->assertEquals('absent', $pattern[0]['color'], 'First letter should be absent due to duplicate handling');
        $this->assertEquals('correct', $pattern[1]['color']);
        $this->assertEquals('correct', $pattern[2]['color']);
        $this->assertEquals('correct', $pattern[3]['color']);
        $this->assertEquals('correct', $pattern[4]['color']);
        $this->assertEquals('absent', $pattern[5]['color']);
    }
}

try {
    $test = new GameControllerTest();
    $test->run();
    echo "GameControllerTest: OK" . PHP_EOL;
} catch (Throwable $throwable) {
    echo 'GameControllerTest: FAIL - ' . $throwable->getMessage() . PHP_EOL;
    exit(1);
}
