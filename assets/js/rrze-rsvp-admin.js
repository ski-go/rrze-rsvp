"use strict";jQuery(document).ready(function(e){rrze_rsvp_admin.dateformat;var r=rrze_rsvp_admin.text_cancel,t=rrze_rsvp_admin.text_cancelled,o=rrze_rsvp_admin.text_confirmed,a=rrze_rsvp_admin.ajaxurl;function n(r,n){var e=n.attr("data-id"),i=n.attr("href");jQuery.ajax({type:"POST",url:a,data:{action:"booking_action",id:e,type:r}}).fail(function(e){console.error("AJAX request failed")}).done(function(e){e=JSON.parse(e),jQuery.ajax({type:"GET",url:i}).fail(function(e){console.error("AJAX request failed")}).done(function(e){"confirm"==r?n.addClass("rrze-rsvp-confirmed").attr("disabled","disabled").html(o):n.attr("disabled","disabled").html(t)})})}e(".rrze-rsvp-confirm").click(function(e){return e.preventDefault(),n("confirm",jQuery(this)),!1}),e(".rrze-rsvp-cancel").click(function(e){return e.preventDefault(),confirm(r)&&n("cancel",jQuery(this)),!1});var i=jQuery("div#rrze-rsvp-booking-details"),s=i.find("select#rrze-rsvp-booking-seat"),c=i.find("input#rrze-rsvp-booking-start_date"),d=i.find("input#rrze-rsvp-booking-start_time"),l=i.find("input#rrze-rsvp-booking-end_date"),v=i.find("input#rrze-rsvp-booking-end_time");d.attr("readonly","readonly"),s.change(function(){c.val(""),d.val(""),l.val(""),v.val(""),jQuery("div.select_timeslot_container").remove()}),c.change(function(){l.val(c.val()),d.val(""),v.val(""),""==s.val()||""==c.val()?alert(rrze_rsvp_admin.alert_no_seat_date):(jQuery("div.select_timeslot_container").remove(),jQuery.post(a,{action:"ShowTimeslots",seat:s.val(),date:c.val()},function(e){0!=e&&jQuery("input#rrze-rsvp-booking-start_time").after(e)}))}),e("div.cmb2-id-rrze-rsvp-booking-start").on("change","select.select_timeslot",function(){var e=jQuery(this).val(),r=jQuery(this).find(":selected").data("end");console.log(e),console.log(r),d.val(e),v.val(r)}),e("div#additional-reservation-functions").hide(),e("div.cmb2-id-rrze-rsvp-room-instant-check-in").hide(),e("select#rrze-rsvp-room-bookingmode").on("change",function(){"additional-reservation-functions"==e("select#rrze-rsvp-room-bookingmode option:checked").val()?e("div#additional-reservation-functions").slideDown(400):e("div#additional-reservation-functions").slideUp(400)}),e("#rrze-rsvp-room-auto-confirmation").click(function(){e(this).is(":checked")?e("div.cmb2-id-rrze-rsvp-room-instant-check-in").slideDown(400):(e("div.cmb2-id-rrze-rsvp-room-instant-check-in").slideUp(400),e("#rrze-rsvp-room-instant-check-in").is(":checked")&&e("#rrze-rsvp-room-instant-check-in").prop("checked",!1))})});