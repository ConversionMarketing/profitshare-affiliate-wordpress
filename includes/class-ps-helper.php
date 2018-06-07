<?php

class PS_Helper
{
    public static function getSubmitSelectScript()
    {
        return "
            $('#ps-status-filter').live('change', function(){
                var status_filter = $(this).val();
                var parameter_name = $(this).attr('parameter_name');
    
                if(!parameter_name) {
                    return;
                }
    
                if(status_filter != ''){
                    document.location.href = location.href + '&'+parameter_name+'='+status_filter;
                    return true;
                }
                document.location.href = removeParam(parameter_name, location.href);
            });
            
            function removeParam(key, sourceURL) {
                var rtn = sourceURL.split('?')[0],
                    param,
                    params_arr = [],
                    queryString = (sourceURL.indexOf('?') !== -1) ? sourceURL.split('?')[1] : '';
                if (queryString !== '') {
                    params_arr = queryString.split('&');
                    for (var i = params_arr.length - 1; i >= 0; i -= 1) {
                        param = params_arr[i].split('=')[0];
                        if (param === key) {
                            params_arr.splice(i, 1);
                        }
                    }
                    rtn = rtn + '?' + params_arr.join('&');
                }
                return rtn;
            }
        ";
    }
}