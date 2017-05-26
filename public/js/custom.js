$(document).ready(function(){
  // Notif hapus
  $(document.body).on('submit','.js-confirm',function(){
    var $el = $(this)
    var text = $el.data('confirm') ? $el.data('confirm') : 'Anda yakin melakukan tindakan ini?'
    var c = confirm(text);
    return c;
  });
  // Selectize
  $('.js-selectize').selectize({
    sortField:'text'
  });
  // Delete review book
  $(document.body).on('submit','.js-review-delete',function(){
    var $el = $(this);
    var text = $el.data('confirm') ? $el.data('confirm') : 'Anda yakin melakukan tindakan ini?';
    var c = confirm(text);
    // Batalkan
    if(c===false) return c;
    // Disable behaviour default dari tombol submit
    event.preventDefault();
    // Hapus via ajax
    $.ajax({
      type:'POST',
      url:$(this).attr('action'),
      dataType:'json',
      data:{
        _method:'DELETE',
        // CSRF token
        _token:$(this).children('input[name]=_token]').val()
      }
    }).done(function(data){
      // Cari baris
      bari = $('#form-'+data.id).closest('tr');
      // Hilangkan bari
      baris.fadeOut(300,function(){$(this).remove()});
    });
  });
});