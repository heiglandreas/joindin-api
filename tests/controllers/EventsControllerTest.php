<?php
/**
 * Copyright (c) Andreas Heigl<andreas@heigl.org>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author    Andreas Heigl<andreas@heigl.org>
 * @copyright Andreas Heigl
 * @license   http://www.opensource.org/licenses/mit-license.php MIT-License
 * @since     10.10.2016
 */

namespace JoindinTest\Controller;

class EventsControllerTest extends \PHPUnit_Framework_TestCase
{
    public function testThatGetActionWithoutCorrespondingDBEntryThrowsException()
    {
        $request = new \Request([], [
            'REQUEST_URI' => 'http://api.dev.joind.in/v2.1/events/1',
            'REQUEST_METHOD' => 'GET'
        ], true);

        $statement = $this->getMockBuilder('\PDOStatement')->disableOriginalConstructor()->getMock();
        $statement->method('execute')->willReturn(false);

        $db = $this->getMockBuilder('\PDO')->disableOriginalConstructor()->getMock();
        $db->method('prepare')->willReturn($statement);

        $controller = new \EventsController();

        try {
            $controller->getAction($request, $db);
            $this->fail('Expected Exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }


    public function testThatGetActionWithCorrespondingDBEntryReturnsList()
    {
        $request = new \Request([], [
            'REQUEST_URI' => 'http://api.dev.joind.in/v2.1/events/1',
            'REQUEST_METHOD' => 'GET'
        ], true);

        $statement = $this->getMockBuilder('\PDOStatement')->disableOriginalConstructor()->getMock();
        $statement->expects($this->at(0))->method('execute')->willReturn(false);
        $statement->expects($this->at(1))->method('execute')->willReturn(true);
        $statement->expects($this->at(2))->method('execute')->willReturn(false);
        $statement->method('fetchAll')->willReturn([[
            "event_name" => "ApacheCon",
            "url_friendly_name" => NULL,
            "event_start" => 1418898156,
            "event_end" => 1418984556,
            'event_lat' => 31.4089910000000000,
            'event_long' => -87.2393260000000000,
            'ID' => 48,
            'event_loc' => 'Repton',
            'event_desc' => 'Duis eu massa justo, vel mollis velit. Vivamus gravida, dolor ut porta bibendum, mauris ligula condimentum est, id facilisis ante massa a justo. Sed nisi sem, ultricies et luctus vitae, volutpat id sem.',
            'active' => 1,
            'event_stub' => '7284a',
            'event_icon' => NULL,
            'pending' => 0,
            'event_hashtag' => '7284a',
            'event_href' => 'http://apachecon.example.org',
            'event_cfp_start' => null,
            'event_cfp_end' => NULL,
            'event_voting' => 0,
            'private' => 0,
            'event_tz_cont' => 'Europe',
            'event_tz_place' => 'Amsterdam',
            'event_contact_name' => null,
            'event_contact_email' => null,
            'event_cfp_url' => 'http://apachecon.example.org/cfp',
            'comment_count' => 0,
            'talk_count' => 6,
            'track_count' => 0,
            'reviewer_id' => null]]);

        $db = $this->getMockBuilder('\PDO')->disableOriginalConstructor()->getMock();
        $db->method('prepare')->willReturn($statement);

        $controller = new \EventsController();

        $expectedValue = ['events' => [
            [
            'name' => 'ApacheCon',
            'url_friendly_name' => 'apachecon',
            'start_date' => '2014-12-18T11:22:36+01:00',
            'end_date' => '2014-12-19T11:22:36+01:00',
            'description' => 'Duis eu massa justo, vel mollis velit. Vivamus gravida, dolor ut porta bibendum, mauris ligula condimentum est, id facilisis ante massa a justo. Sed nisi sem, ultricies et luctus vitae, volutpat id sem.',
            'stub' => '7284a',
            'href' => 'http://apachecon.example.org',
            'tz_continent' => 'Europe',
            'tz_place' => 'Amsterdam',
            'attendee_count' => null,
            'attending' => false,
            'event_average_rating' => null,
            'event_comments_count' => 0,
            'tracks_count' => 0,
            'talks_count' => 6,
            'icon' => null,
            'location' => 'Repton',
            'images' => Array (),
            'tags' => Array (),
            'uri' => 'http:////events/48',
            'verbose_uri' => 'http:////events/48?verbose=yes',
            'comments_uri' => 'http:////events/48/comments',
            'talks_uri' => 'http:////events/48/talks',
            'tracks_uri' => 'http:////events/48/tracks',
            'attending_uri' => 'http:////events/48/attending',
            'images_uri' => 'http:////events/48/images',
            'website_uri' => '/event/',
            'humane_website_uri' => '/e/7284a',
            'attendees_uri' => 'http:////events/48/attendees',
            ],
        ], 'meta'=> [
            'count' => 1,
            'total' => null,
            'this_page' => 'http:///v2.1/events/1?resultsperpage=20',
        ]];
        $this->assertEquals($expectedValue, $controller->getAction($request, $db));
    }
}
