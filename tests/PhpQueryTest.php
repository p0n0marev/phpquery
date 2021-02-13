<?php

use PHPUnit\Framework\TestCase;
use PhpQuery\DOMDocumentWrapper;
use PhpQuery\PhpQuery;

class PhpQueryTest extends TestCase
{
    public $markup = '
        <div>
            <ul id="items">
                <li>Item 1</li>
                <li class="active">Item 2</li>
                <li>Item 3</li>
            </ul>
          <div id="block"><span>1</span></div>
          <form>
            <input type="text" name="input-text" value="Input Text" />
            <input type="checkbox" name="input-checkbox" />
            <input type="checkbox" name="input-checkbox-checked" checked />
            <input type="radio" name="input-radio" value="radio1" checked/>
            <input type="radio" name="input-radio" value="radio2" />
            <select name="select">
                <option value="1">Item 1</option>
                <option value="2" selected>Item 2</option>
                <option value="3">Item 3</option>
            </select>
          </form>
        </div>
        ';

    public function testExist()
    {
        $pq = new PhpQuery($this->markup);

        $this->assertEquals(1, $pq->find('#items')->length());
        $this->assertEquals(3, $pq->find('li')->length());
        $this->assertEquals('items', $pq->find('#items')->attr('id'));
        $this->assertEquals('active', $pq->find('.active')->attr('class'));
        $this->assertEquals('Item 2', $pq->find('.active')->text());

        $this->assertEquals('1', $pq->find('#block')->html()->find('span')->text());
        $this->assertEquals($pq->find('li')->length(), $pq->find('#block')->find('li')->length());
    }

    public function testEach()
    {
        $pq = new PhpQuery($this->markup);

        foreach ( $pq->find('li') as $index => $li ) {
            $this->assertEquals(sprintf('Item %d', $index + 1), $li->textContent);
        }

        $expected = [
            'Item 1',
            'Item 2',
            'Item 3',
        ];
        $actual = [];
        $pq->find('li')->each(function($li) use (&$actual) {
            $actual[] = $li->textContent;
        });

        $this->assertEquals($expected, $actual);
    }

    public function testForm()
    {
        $pq = new PhpQuery($this->markup);

        $this->assertEquals('Input Text', $pq->find('[name=input-text]')->val());
        $this->assertEquals('2', $pq->find('[name=select]')->val());
    }
}
