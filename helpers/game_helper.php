<?php

function splitToCharacters(string $word): array
{
    if (function_exists('mb_str_split')) {
        return mb_str_split($word);
    }

    return preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function evaluateMotusGuess(string $secretWord, string $guessWord): array
{
    $secretWord = strtoupper(trim($secretWord));
    $guessWord = strtoupper(trim($guessWord));

    $secretLetters = splitToCharacters($secretWord);
    $guessLetters = splitToCharacters($guessWord);

    $feedback = [];
    $letterAvailability = [];

    foreach ($secretLetters as $letter) {
        $letterAvailability[$letter] = ($letterAvailability[$letter] ?? 0) + 1;
    }

    $length = count($secretLetters);

    for ($index = 0; $index < $length; $index++) {
        $letter = $guessLetters[$index] ?? '';
        if ($letter !== '' && $letter === $secretLetters[$index]) {
            $feedback[$index] = [
                'letter' => $letter,
                'status' => 'correct',
            ];
            $letterAvailability[$letter]--;
        }
    }

    for ($index = 0; $index < $length; $index++) {
        if (isset($feedback[$index])) {
            continue;
        }

        $letter = $guessLetters[$index] ?? '';

        if ($letter === '') {
            $feedback[$index] = [
                'letter' => '',
                'status' => 'absent',
            ];
            continue;
        }

        if (($letterAvailability[$letter] ?? 0) > 0) {
            $feedback[$index] = [
                'letter' => $letter,
                'status' => 'present',
            ];
            $letterAvailability[$letter]--;
        } else {
            $feedback[$index] = [
                'letter' => $letter,
                'status' => 'absent',
            ];
        }
    }

    ksort($feedback);

    return [
        'feedback' => array_values($feedback),
        'isWinning' => $secretWord === $guessWord,
    ];
}