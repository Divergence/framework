<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Tests\Models;

use PHPUnit\Framework\TestCase;
use Divergence\Helpers\Validate;
use Divergence\Models\RecordValidator;
use Divergence\Tests\MockSite\Models\Canary;

use Divergence\Tests\Models\Testables\TestableRecordValidator;

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

    public function testValidateStringRequiredSuccess()
    {
        $Record = [];

        $Record['Name'] = 'Kelly';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Name',
            'required' => true,
            'errorMessage' => 'Name is required.',
        ]);
        $this->assertEquals([], $v->getErrors());
    }

    public function testValidateStringRequiredFail()
    {
        $Record = [];

        $Record['Name'] = null;

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Name',
            'required' => true,
            'errorMessage' => 'Name is required.',
        ]);
        $this->assertEquals(['Name'=>'Name is required.'], $v->getErrors());
    }

    public function testValidateStringMinLengthFail()
    {
        $Record = [];

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

    public function testValidateStringMinLengthSuccess()
    {
        $Record = [];

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

    public function testValidateStringMaxLengthFail()
    {
        $Record = [];

        $Record['Name'] = 'arebel';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Name',
            'maxlength' => 5,
            'required' => true,
            'errorMessage' => 'Name is too big. Max 5 characters.',
        ]);
        $this->assertEquals(['Name'=>'Name is too big. Max 5 characters.'], $v->getErrors());
    }

    public function testValidateStringMaxLengthSuccess()
    {
        $Record = [];

        $Record['Name'] = 'rebel';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Name',
            'maxlength' => 5,
            'required' => true,
            'errorMessage' => 'Name is too big. Max 5 characters.',
        ]);
        $this->assertEquals([], $v->getErrors());
    }

    public function testValidateNumberSuccess()
    {
        $Record = [];

        $Record['ID'] = 1;

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'ID',
            'required' => true,
            'validator' => 'number',
            'max' => PHP_INT_MAX,
            'min' => 1,
            'errorMessage' => 'ID must be between 0 and PHP_INT_MAX ('.PHP_INT_MAX.')',
        ]);
        $this->assertEquals([], $v->getErrors());
    }

    public function testValidateNumberFailZero()
    {
        $Record = [];

        $Record['ID'] = 0;

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'ID',
            'required' => true,
            'validator' => 'number',
            'max' => PHP_INT_MAX,
            'min' => 1,
            'errorMessage' => 'ID must be between 0 and PHP_INT_MAX ('.PHP_INT_MAX.')',
        ]);
        $this->assertEquals(['ID'=>'ID must be between 0 and PHP_INT_MAX ('.PHP_INT_MAX.')'], $v->getErrors());
    }

    public function testValidateNumberFailMax()
    {
        $Record = [];

        $Record['ID'] = PHP_INT_MAX * 2;

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'ID',
            'required' => true,
            'validator' => 'number',
            'max' => PHP_INT_MAX,
            'min' => 1,
            'errorMessage' => 'ID must be between 0 and PHP_INT_MAX ('.PHP_INT_MAX.')',
        ]);
        $this->assertEquals(['ID'=>'ID must be between 0 and PHP_INT_MAX ('.PHP_INT_MAX.')'], $v->getErrors());
    }

    public function testValidateNumberFailFloatMax()
    {
        $Record = [];

        $Record['Float'] = 1;

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Float',
            'required' => true,
            'validator' => 'number',
            'max' => 0.759,
            'min' => 0.128,
            'errorMessage' => 'ID must be between 0.127 and 0.760',
        ]);
        $this->assertEquals(['Float'=>'ID must be between 0.127 and 0.760'], $v->getErrors());
    }

    public function testValidateNumberFailFloatMin()
    {
        $Record = [];

        $Record['Float'] = 0.127;

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Float',
            'required' => true,
            'validator' => 'number',
            'max' => 0.759,
            'min' => 0.128,
            'errorMessage' => 'ID must be between 0.127 and 0.760',
        ]);
        $this->assertEquals(['Float'=>'ID must be between 0.127 and 0.760'], $v->getErrors());
    }

    public function testValidateNumberSuccessFloat()
    {
        $Record = [];

        $Record['Float'] = 0.128;

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Float',
            'required' => true,
            'validator' => 'number',
            'max' => 0.759,
            'min' => 0.128,
            'errorMessage' => 'ID must be between 0.127 and 0.760',
        ]);
        $this->assertEquals([], $v->getErrors());
    }


    public function testValidateEmailSuccess()
    {
        $Record = [];

        $Record['Email'] = 'henry.paradiz@gmail.com';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Email',
            'required' => true,
        ]);
        $this->assertEquals([], $v->getErrors());
    }

    public function testValidateEmailSuccessWithCustomValidator()
    {
        $Record = [];

        $Record['Email'] = 'henry.paradiz@gmail.com';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Email',
            'required' => true,
            'validator' => [
                Validate::class,
                'email',
            ],
        ]);
        $this->assertEquals([], $v->getErrors());
    }

    public function testValidateRequiredOptionFieldException()
    {
        $Record = [];

        $v = new TestableRecordValidator($Record);
        $this->expectExceptionMessage('Required option "field" missing');
        $v->validate([
            'required' => true,
            'validator' => 'email',
        ]);
    }

    public function testValidateEmailFail()
    {
        $Record = [];

        $Record['Email'] = 'henry.paradiz|gmail.com';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Email',
            'required' => true,
            'validator' => 'email',
        ]);
        $this->assertEquals(['Email'=>'Email is invalid.'], $v->getErrors());
        $this->assertEquals('Email is invalid.', $v->getErrors('Email'));
        $this->assertFalse($v->getErrors('fake'));
        $this->assertTrue($v->hasErrors('Email'));
        $this->assertFalse($v->hasErrors('fake'));
        $this->assertTrue($v->hasErrors());
    }

    public function testValidateNotACallableException()
    {
        $Record = [];
        $Record['something'] = 'anything';
        $v = new TestableRecordValidator($Record);
        $this->expectExceptionMessage('Validator for field something is not callable');
        $v->validate([
            'field' => 'something',
            'required' => true,
            'errorMessage' => 'Fail whale',
            'validator' => new \stdClass(),
        ]);
    }

    public function testValidateCustomValidatorSuccess()
    {
        $Record = Canary::avis();

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'DNA',
            'required' => true,
            'errorMessage' => 'Not a valid DNA sequence',
            'validator' => function ($value, $options) {
                preg_match_all('/^[ACGT]*$/m', $value, $matches, PREG_SET_ORDER, 0);
                return is_array($matches);
            },
        ]);
        $this->assertEquals([], $v->getErrors());
    }

    public function testValidateCustomValidatorFail()
    {
        $Record = Canary::avis();

        $Record['DNA'][67] = 'X';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'DNA',
            'required' => true,
            'errorMessage' => 'Not a valid DNA sequence',
            'validator' => function ($value, $options) {
                preg_match_all('/^[ACGT]*$/m', $value, $matches, PREG_SET_ORDER, 0);
                return is_array($matches);
            },
        ]);
        $this->assertEquals([], $v->getErrors());
    }

    public function testValidateTwoErrorsSameField()
    {
        $Record = [];

        $Record['Email'] = '';

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Email',
            'required' => true,
            'validator' => 'string',
            'errorMessage' => 'Email required',
        ]);

        // if there are two validation errors on the same field the first will not be overwritten
        $v->validate([
            'field' => 'Email',
            'required' => true,
            'validator' => 'email',
        ]);
        $this->assertEquals(['Email'=>'Email required'], $v->getErrors());
    }

    public function testValidateAddError()
    {
        $Record = [];

        $v = new TestableRecordValidator($Record);
        $v->addError('id', 'message');

        $this->assertEquals(['id'=>'message'], $v->getErrors());
        $this->assertEquals('message', $v->getErrors('id'));
    }

    public function testValidateNonExistantField()
    {
        $Record = [];

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Email',
            'required' => true,
            'validator' => 'email',
        ]);
        $this->assertEquals(['Email'=>'Email is missing.'], $v->getErrors());
    }

    public function testValidateNonExistantFieldNotRequired()
    {
        $Record = [];

        $v = new TestableRecordValidator($Record);
        $v->validate([
            'field' => 'Email',
            'required' => false,
            'validator' => 'email',
        ]);
        $this->assertEquals([], $v->getErrors());
    }
}
