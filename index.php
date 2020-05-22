<?php

$rootPath = __DIR__;
$storagePath = $rootPath . '/storage';
$exportPath = $rootPath . '/output';
$songbookName = 'SongBookName'; //Change this

if (!file_exists($exportPath)) mkdir($exportPath);
if (!file_exists($storagePath)) mkdir($storagePath);
if (!file_exists($exportPath . '/plain_text')) mkdir($exportPath . '/plain_text');
if (!file_exists($exportPath . '/xml')) mkdir($exportPath . '/xml');

try {
    if (!file_exists($storagePath . '/Songs.json')) throw new Exception('Import file is not exists');
    $data = json_decode(file_get_contents($storagePath . '/Songs.json'), true);

    $songs = [];
    foreach ($data as $song) {
        if (isset($song['songbook_id']) && $song['songbook_id'] != 6) continue;
        $song_text = $song['song_text'];
        $title = $song['title'];
        $number = $song['number'];

        /* get the xml printed */
        file_put_contents($exportPath . '/plain_text' . '/' . $song['number'] . '-' . $song['title'] . '.txt', $song_text);

        $songStructure = [
            'title' => $title,
            'number' => $number,
            'parts' => []
        ];

        $verses = explode('Куплет', $song_text);
        $countChoruses = 0;
        $countVerses = 0;


        foreach ($verses as $index => $vers) {
            if (empty($vers)) {
                continue;
            }
            $case1 = strpos($vers, 'Припев') !== false;
            $case2 = strpos($vers, 'Приспів') !== false;

            if ($case1 || $case2) {
                $key = $case2 ? 'Приспів' : 'Припев';
                $searchChorus = explode($key, $vers);
                $songStructure['parts'][] = [
                    'type' => 'Verse',
                    'number' => $countVerses + 1,
                    'data' => trim($searchChorus[0])
                ];

                $songStructure['parts'][] = [
                    'type' => 'Chorus',
                    'number' => $countChoruses + 1,
                    'data' => trim($searchChorus[1])
                ];
                $countChoruses++;
                $countVerses++;
            } else {
                $songStructure['parts'][] = [
                    'type' => 'Verse',
                    'number' => $countVerses + 1,
                    'data' => trim($vers)
                ];
                $countVerses++;
            }


        }
        $songs[] = $songStructure;

    }


    foreach ($songs as $song) {
        $domtree = new DOMDocument('1.0', 'UTF-8');
        $verseOrders = [];
        /* create the root element of the xml tree */
        $songElement = $domtree->createElement("song");

        $songElement->setAttribute('xmlns', 'http://openlyrics.info/namespace/2009/song');
        $songElement->setAttribute('version', '0.8');
        $songElement->setAttribute('createdIn', 'OpenLP 2.4.6');
        $songElement->setAttribute('modifiedIn', 'OpenLP 2.4.6');
        $songElement->setAttribute('modifiedDate', '2020-05-09T22:15:25');

        /* append it to the document created */
        $songElement = $domtree->appendChild($songElement);
        $properties = $domtree->createElement("properties");
        $lyrics = $domtree->createElement('lyrics');
        foreach ($song['parts'] as $part) {
            $lines = $domtree->createElement('lines', $part['data']);
            if ($part['type'] == 'Verse') {
                $verseOrders[] = 'v' . $part['number'];
                $verse = $domtree->createElement('verse');
                $verse->setAttribute('name', 'v' . $part['number']);
                $verse->appendChild($lines);
                $lyrics->appendChild($verse);
            }
            if ($part['type'] == 'Chorus') {
                $verseOrders[] = 'c' . $part['number'];
                $verse = $domtree->createElement('chorus');
                $verse->setAttribute('name', 'c' . $part['number']);
                $verse->appendChild($lines);
                $lyrics->appendChild($verse);
            }
        }
        $verseOrder = $domtree->createElement('verseOrder', implode(' ', $verseOrders));
        //Authors
        $authors = $domtree->createElement('authors');
        $authors->appendChild($domtree->createElement('author', $songbookName));

        $title = $domtree->createElement('title', $song['number'] . '.' . $song['title']);
        $title2 = $domtree->createElement('title', $song['number']);
        $titles = $domtree->createElement('titles');
        $titles->appendChild($title);
        $titles->appendChild($title2);

        $songbooks = $domtree->createElement('songbooks');
        $songbook = $domtree->createElement('songbook');

        $songbook->setAttribute('name', $songbookName);
        $songbook->setAttribute('entry', $song['number']);

        $songbooks->appendChild($songbook);

        $properties->appendChild($titles);
        $properties->appendChild($verseOrder);
        $properties->appendChild($authors);
        $properties->appendChild($songbooks);

        $songElement->appendChild($properties);
        $songElement->appendChild($lyrics);

        file_put_contents($exportPath . '/xml/' . $song['number'] . '-' . $song['title'] . '.xml', $domtree->saveXML());
    }

    print_r('Job done!'. PHP_EOL);
} catch (Exception $exception) {
    print_r($exception->getMessage() . PHP_EOL);
}