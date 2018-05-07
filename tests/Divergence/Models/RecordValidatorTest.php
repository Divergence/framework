<?php
namespace Divergence\Tests\Models;

use PHPUnit\Framework\TestCase;
use Divergence\Models\RecordValidator;
use Divergence\Tests\MockSite\Models\Canary;

class TestableRecordValidator extends RecordValidator
{
    public function getProtected($field)
    {
        return $this->$field;
    }
}


class RecordValidatorTest extends TestCase
{
    public function test__construct()
    {
        $c = Canary::getByID(1)->data;
        $v = new TestableRecordValidator($c);
        $this->assertEquals($c, $v->getProtected('_record'));

        // careless user input whitespaces
        $untrimmedRecord = Canary::avis();
        $untrimmedRecord['Name'] = "\t".$untrimmedRecord['Name'].' ';
        $untrimmedRecord['Handle'] = "\t".$untrimmedRecord['Handle'].' ';
        $a = strlen($untrimmedRecord['Name']);
        $b  = strlen($untrimmedRecord['Handle']);
        $v2 = new TestableRecordValidator($untrimmedRecord);
        $this->assertNotEquals($a, strlen($v2->getProtected('_record')['Name']));
        $this->assertNotEquals($b, strlen($v2->getProtected('_record')['Handle']));
    }

    public function testValidate()
    {
        $Record = Canary::avis();

        $Record['Name'] = 'A';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Name',
            'minlength' => 2,
            'required' => true,
            'errorMessage' => 'Name is required.',
        ]);
        $this->assertEquals(['Name'=>'Name is required.'], $v->getErrors());
    }
}
