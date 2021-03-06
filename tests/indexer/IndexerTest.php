<?php

use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;
use TeamTNT\TNTSearch\TNTSearch;

class TNTIndexerTest extends PHPUnit_Framework_TestCase
{
    protected $indexName = "testIndex";
    protected $config    = [
        'driver'   => 'sqlite',
        'database' => __DIR__ . '/../_files/articles.sqlite',
        'host'     => 'localhost',
        'username' => 'testUser',
        'password' => 'testPass',
        'storage'  => __DIR__ . '/../_files/',
    ];

    public function testSearch()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $tnt->asYouType = true;
        $res            = $tnt->search('Juliet');

        //the most relevant doc has the id 9
        $this->assertEquals("9", $res['ids'][0]);

        $res = $tnt->search('Queen Mab');
        $this->assertEquals([7], $res['ids']);
    }

    public function testIndexFromFileSystem()
    {
        $config = [
            'driver'    => 'filesystem',
            'storage'   => __DIR__ . '/../_files/',
            'location'  => __DIR__ . '/../_files/articles/',
            'extension' => 'txt',
        ];

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);
        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $index = $tnt->getIndex();
        $count = $index->countWordInWordList('document');

        $this->assertTrue($count == 3, 'Word document should be 3');
    }

    public function testIfCroatianStemmerIsSet()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->setLanguage('croatian');
        $indexer->disableOutput = true;
        $indexer->run();

        $this->index = new PDO('sqlite:' . $this->config['storage'] . $this->indexName);
        $query       = "SELECT * FROM info WHERE key = 'stemmer'";
        $docs        = $this->index->query($query);
        $value       = $docs->fetch(PDO::FETCH_ASSOC)['value'];
        $this->assertEquals('croatian', $value);
    }

    public function testIfGermanStemmerIsSet()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->setLanguage('german');
        $indexer->disableOutput = true;
        $indexer->run();

        $this->index = new PDO('sqlite:' . $this->config['storage'] . $this->indexName);
        $query       = "SELECT * FROM info WHERE key = 'stemmer'";
        $docs        = $this->index->query($query);
        $value       = $docs->fetch(PDO::FETCH_ASSOC)['value'];
        $this->assertEquals('german', $value);
    }

    public function testBuildTrigrams()
    {
        $indexer  = new TNTIndexer;
        $trigrams = $indexer->buildTrigrams('created');
        $this->assertEquals('__c _cr cre rea eat ate ted ed_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('mood');
        $this->assertEquals('__m _mo moo ood od_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('death');
        $this->assertEquals('__d _de dea eat ath th_ h__', $trigrams);

        $trigrams = $indexer->buildTrigrams('behind');
        $this->assertEquals('__b _be beh ehi hin ind nd_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('usually');
        $this->assertEquals('__u _us usu sua ual all lly ly_ y__', $trigrams);

        $trigrams = $indexer->buildTrigrams('created');
        $this->assertEquals('__c _cr cre rea eat ate ted ed_ d__', $trigrams);

    }

    public function tearDown()
    {
        if (file_exists(__DIR__ . '/../_files/' . $this->indexName)) {
            unlink(__DIR__ . '/../_files/' . $this->indexName);
        }
    }

    public function testSetTokenizer()
    {
        $someTokenizer = new SomeTokenizer;

        $indexer = new TNTIndexer;
        $indexer->setTokenizer($someTokenizer);

        $this->assertInstanceOf(TokenizerInterface::class, $indexer->tokenizer);

        $res = $indexer->breakIntoTokens('Canon 70-200');
        $this->assertContains("canon", $res);
        $this->assertContains("70-200", $res);
    }

    public function testCustomPrimaryKey()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->setPrimaryKey('post_id');
        $indexer->disableOutput = true;
        $indexer->query('SELECT * FROM posts;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->search('second');

        //the most relevant doc has the id 9
        $this->assertEquals("2", $res['ids'][0]);
    }
}

class SomeTokenizer implements TokenizerInterface
{

    public function tokenize($text)
    {
        return preg_split("/[^\p{L}\p{N}-]+/u", mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    }
}
