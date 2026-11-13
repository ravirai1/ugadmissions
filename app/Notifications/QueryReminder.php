<?php

namespace App\Notifications;

class QueryReminder extends QueryRaised
{
    protected function subjectLine(): string
    {
        return 'Reminder: reply needed on application '.$this->query->application->application_no." – St. Stephen's College";
    }
}
