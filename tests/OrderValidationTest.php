<?php

use PHPUnit\Framework\TestCase;

class OrderValidationTest extends TestCase
{
    public function testEmptyPhoneValidation()
    {
        $phone = '';
        $errors = [];
        
        if (empty($phone) || !preg_match('/^\+?[0-9\s\-()]{10,18}$/', $phone)) {
            $errors[] = 'Некорректный номер телефона';
        }
        
        $this->assertContains('Некорректный номер телефона', $errors);
        $this->assertNotEmpty($errors);
    }

    public function testValidPhone()
    {
        $validPhones = ['+79991234567', '8 (999) 123-45-67'];
        
        foreach ($validPhones as $phone) {
            $isValid = preg_match('/^\+?[0-9\s\-()]{10,18}$/', $phone);
            $this->assertEquals(1, $isValid);
        }
    }

    public function testEmptyNameValidation()
    {
        $name = '';
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Укажите ФИО';
        }
        
        $this->assertContains('Укажите ФИО', $errors);
    }
}