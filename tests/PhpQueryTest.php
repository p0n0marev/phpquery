<?php

use P0n0marev\PhpQuery\Tests;
use PHPUnit\Framework\TestCase;

class PhpQueryTest extends TestCase
{
    private PhpQuery $pq;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $data = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'test.html');
        $this->pq = new PhpQuery($data);
    }

    public function testLength()
    {
        $this->assertEquals(1, $this->pq->find('#items')->length());
        $this->assertEquals(3, $this->pq->find('#items li')->length());
        $this->assertEquals(3, $this->pq->find('li')->length());
        $this->assertEquals(2, $this->pq->find('input')->find('[type="radio"]]')->count());
        $this->assertEquals(2, $this->pq->find('.radio')->count());
    }

    public function testGetValues()
    {
        $this->assertEquals('items', $this->pq->find('#items')->attr('id'));
        $this->assertEquals('active', $this->pq->find('.active')->attr('class'));
        $this->assertEquals('Item 2', $this->pq->find('.active')->text());
        $this->assertEquals('Span Text', $this->pq->find('#block')->find('span')->text());
        $this->assertEquals('Option 3', $this->pq->find('select option')->last()->text());
        $this->assertEquals('<div id="block"><span>Span Text</span></div>', $this->pq->find('#block')->html());
    }

    public function testSetValues()
    {
        $this->assertEquals('New Value', $this->pq->find('#block')
            ->attr('new-attr', 'New Value')->attr('new-attr'));
    }

    public function testEach()
    {
        $this->pq->find('li')->each(function ($li, $index) {
            $this->assertEquals(sprintf('Item %d', $index + 1), $li->text());
        });
    }
}
