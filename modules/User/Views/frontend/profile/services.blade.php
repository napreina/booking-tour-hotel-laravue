<?php
$types = get_bookable_services();
if(empty($types)) return;
?>
<div class="profile-service-tabs">
    <div class="service-nav-tabs">
        <ul class="nav nav-tabs">
            @php $i = 0; @endphp
            @foreach($types as $type=>$moduleClass)
                <li class="nav-item">
                    <a class="nav-link @if(!$i) active @endif" data-toggle="tab" href="#type_{{$type}}">{{$moduleClass::getModelName()}}</a>
                </li>
                @php $i++; @endphp
            @endforeach
        </ul>
    </div>
    <div class="tab-content">
        @php $i = 0; @endphp
        @foreach($types as $type=>$moduleClass)
            @if(view()->exists(ucfirst($type).'::frontend.profile.service') && $user->hasPermissionTo($type.'_create'))
                <div class="tab-pane fade @if(!$i) show active @endif" id="type_{{$type}}" role="tabpanel" aria-labelledby="pills-home-tab">
                    @include(ucfirst($type).'::frontend.profile.service')
                </div>
            @endif
            @php $i++; @endphp
        @endforeach
    </div>
</div>

