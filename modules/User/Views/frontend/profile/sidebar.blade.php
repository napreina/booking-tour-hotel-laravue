<div class="profile-summary">
    <div class="profile-avatar">
        @if($avatar = $user->getAvatarUrl())
            <img src="{{$avatar}}" alt="{{$user->getDisplayName()}}">
        @else
            <span class="avatar-text">{{$user->getDisplayName()[0]}}</span>
        @endif
    </div>
    <h3 class="display-name">{{$user->getDisplayName()}}</h3>
    <p class="profile-since">{{ __("Member Since :time",["time"=> date("M Y",strtotime($user->created_at))]) }}</p>

    @if($user->hasPermissionTo('dashboard_vendor_access'))<hr>
    <ul class="meta-info style2">
        <li class="is_vendor">
            <i class="icon ion-ios-ribbon"></i>
            {{__('Vendor')}}
        </li>
        <li class="review_count">
            <i class="icon ion-ios-thumbs-up"></i>
            @if($user->review_count <= 1)
                {{__(':count review',['count'=>$user->review_count])}}
            @else
                {{__(':count reviews',['count'=>$user->review_count])}}
            @endif
        </li>
    </ul>
    @endif
    <hr>
    <ul class="meta-info style1">
        @if(setting_item('vendor_show_email'))
        <li class="user_email">
            <span class="label">{{__('Email:')}}</span>
            <span class="val">{{$user->email}}</span>
        </li>
        @endif

        @if(setting_item('vendor_show_phone'))
        <li class="user_phone">
            <span class="label">{{__('Phone:')}}</span>
            <span class="val">{{$user->phone}}</span>
        </li>
        @endif
    </ul>
</div>
