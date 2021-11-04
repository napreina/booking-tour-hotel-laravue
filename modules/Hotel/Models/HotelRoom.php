<?php

namespace Modules\Hotel\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Modules\Booking\Models\Bookable;
use Modules\Booking\Models\Booking;
use Modules\Core\Models\SEO;
use Modules\Media\Helpers\FileHelper;
use Modules\Review\Models\Review;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Hotel\Models\HotelTranslation;
use Modules\User\Models\UserWishList;

class HotelRoom extends Bookable
{
    use SoftDeletes;
    protected $table = 'bravo_hotel_rooms';
    public $type = 'hotel_room';

    protected $fillable = [
        'title',
        'content',
        'status',
    ];

    protected $seo_type = 'hotel_room';


    protected $bookingClass;
    protected $roomDateClass;
    protected $hotelRoomTermClass;
    protected $roomBookingClass;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->bookingClass = Booking::class;
        $this->roomDateClass = HotelRoomDate::class;
        $this->hotelRoomTermClass = HotelRoomTerm::class;
        $this->roomBookingClass = HotelRoomBooking::class;
    }

    public static function getModelName()
    {
        return __("Hotel Room");
    }

    public static function getTableName()
    {
        return with(new static)->table;
    }


    public function terms(){
        return $this->hasMany($this->hotelRoomTermClass, "target_id");
    }

    public function isAvailableAt($filters = []){

        if(empty($filters['start_date']) or empty($filters['end_date'])) return true;

        //Adult - Children
        if( !empty($filters['adults']) and $this->adults < $filters['adults'] ){
            return false;
        }
        if( !empty($filters['children']) and $this->children < $filters['children'] ){
            return false;
        }

        $roomDates =  $this->getDatesInRange($filters['start_date'],$filters['end_date']);
        $allDates = [];
        $tmp_price = 0;
        $tmp_night = 0;
        for($i = strtotime($filters['start_date']); $i < strtotime($filters['end_date']); $i+= DAY_IN_SECONDS)
        {
            $allDates[date('Y-m-d',$i)] = [
                'number'=>$this->number,
                'price'=>$this->price
            ];
            $tmp_night++;
        }

        if(!empty($roomDates))
        {
            foreach ($roomDates as $row)
            {
                if(!$row->active or !$row->number or !$row->price) return false;
                $allDates[date('Y-m-d',strtotime($row->start_date))] = [
                    'number'=>$row->number,
                    'price'=>$row->price
                ];
            }
        }

        $roomBookings = $this->getBookingsInRange($filters['start_date'],$filters['end_date']);
        if(!empty($roomBookings)){
            foreach ($roomBookings as $roomBooking){
                for($i = strtotime($roomBooking->start_date); $i <= strtotime($roomBooking->end_date); $i+= DAY_IN_SECONDS)
                {
                    if(!array_key_exists(date('Y-m-d',$i),$allDates)) continue;
                    $allDates[date('Y-m-d',$i)]['number'] -= $roomBooking->number;

                    if($allDates[date('Y-m-d',$i)]['number'] <= 0){
                        return false;
                    }
                }
            }
        }

        $this->tmp_number = min(array_column($allDates,'number'));
        if(empty($this->tmp_number)) return false;

        $this->tmp_price = array_sum(array_column($allDates,'price'));
        $this->tmp_dates = $allDates;
        $this->tmp_nights = $tmp_night;

        return true;
    }

    public function getDatesInRange($start_date,$end_date)
    {
        $query = $this->roomDateClass::query();
        $query->where('target_id',$this->id);
        $query->where('start_date','>=',date('Y-m-d H:i:s',strtotime($start_date)));
        $query->where('end_date','<=',date('Y-m-d H:i:s',strtotime($end_date)));

        return $query->take(40)->get();
    }

    public function getBookingsInRange($from, $to)
    {
       return $this->roomBookingClass::query()->where('room_id',$this->id)->active()->inRange($from,$to)->get();
    }


    public function getGallery($featuredIncluded = false)
    {
        if (empty($this->gallery))
            return $this->gallery;
        $list_item = [];
        if ($featuredIncluded and $this->image_id) {
            $list_item[] = [
                'large' => FileHelper::url($this->image_id, 'full'),
                'thumb' => FileHelper::url($this->image_id, 'thumb')
            ];
        }
        $items = explode(",", $this->gallery);
        foreach ($items as $k => $item) {
            $large = FileHelper::url($item, 'full');
            $thumb = FileHelper::url($item, 'thumb');
            $list_item[] = [
                'large' => $large,
                'thumb' => $thumb
            ];
        }
        return $list_item;
    }
}
