<?php

$whitespace = "\t\n\x0b\x0c\r ";
$lowercase = "aàâbcçdeéèêëfghiîïjklmnoôpqrstuùûüvwxyz";
$uppercase = "AÀÂBCÇDEÉÈÊËFGHIÎÏJKLMNOÔPQRSTUÙÛÜVWXYZ";
$letters = $lowercase.$uppercase;
$digits = '0123456789';
$hexdigits = '0123456789abcdefABCDEF';
$octdigits = '01234567';
$punctuation = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';
$printable = $digits.$letters.$punctuation.$whitespace;


$accents = array(
    'a' => array('à', 'â'),
    'c' => array('ç'),
    'e' => array('é', 'è', 'ê', 'ë'),
    'i' => array('î', 'ï'),
    'o' => array('ô'),
    'u' => array('ù', 'û', 'ü'),
    'A' => array('À', 'Â'),
    'C' => array('Ç'),
    'E' => array('É', 'È', 'Ê', 'Ë'),
    'I' => array('Î', 'Ï'),
    'O' => array('Ô'),
    'U' => array('Ù', 'Û', 'Ü')
);


function unaccentuate($string) {
    global $accents;
    
    foreach ($accents as $replace => $searches) {
        foreach ($searches as $search) {
            $string = str_replace($search, $replace, $string);
        }
    }
    return $string;
}


function trim_slashes($string) {
    $string = trim($string, '/');
    return $string;
}


function trim_quotes($string) {
    $string = trim($string, '\'"');
    return $string;
}

?>
