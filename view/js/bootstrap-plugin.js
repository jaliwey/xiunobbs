
// 0 表示永不超时，
$.alert = function(subject, timeout, options) {
	var options = options || {};
	var t = timeout || 3;
	var s = '\
	<div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">\
		<div class="modal-dialog modal-md">\
			<div class="modal-content">\
				<div class="modal-header">\
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">\
						<span aria-hidden="true">&times;</span>\
					</button>\
					<h4 class="modal-title">'+lang.tips_title+'</h4>\
				</div>\
				<div class="modal-body">\
					<h5>'+subject+'</h5>\
				</div>\
				<div class="modal-footer">\
					<button type="button" class="btn btn-secondary" data-dismiss="modal">'+lang.close+'</button>\
				</div>\
			</div>\
		</div>\
	</div>';
	var jmodal = $(s).appendTo('body');
	jmodal.modal('show');
	if(timeout != 0) {
		setTimeout(function() {
			jmodal.modal('hide');
		}, t * 1000);
	}
}

$.confirm = function(subject, ok_callback, options) {
	var options = options || {};
	var t = options.timeout || 3;
	var s = '\
	<div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">\
		<div class="modal-dialog modal-md">\
			<div class="modal-content">\
				<div class="modal-header">\
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">\
						<span aria-hidden="true">&times;</span>\
					</button>\
					<h4 class="modal-title">'+lang.confirm_title+'</h4>\
				</div>\
				<div class="modal-body">\
					<h5>'+subject+'</h5>\
				</div>\
				<div class="modal-footer">\
					<button type="button" class="btn btn-primary">'+lang.confirm+'</button>\
					<button type="button" class="btn btn-secondary" data-dismiss="modal">'+lang.close+'</button>\
				</div>\
			</div>\
		</div>\
	</div>';
	var jmodal = $(s).appendTo('body');
	jmodal.find('.modal-footer').find('.btn-primary').on('click', function() {
		jmodal.modal('hide');
		if(ok_callback) ok_callback();
	});
	jmodal.modal('show');
}
