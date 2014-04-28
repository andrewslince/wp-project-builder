function dbg(param)
{
    window.console.log(param);
}

function ajax(options)
{
    var req    = null,
        paramsCount = 0,
        arrayParams = [],
        params = "";

    // figure out what kind of support we have for the XMLHttpRequest object
    req = (window.XMLHttpRequest)
        ? new XMLHttpRequest()  //modern browsers
        : new ActiveXObject("Microsoft.XMLHTTP");  //good ol' lousy IE

    // setup the readystatechange listener
    req.onreadystatechange = function ()
    {
        //right now we only care about a successful and complete response
        if (req.readyState === 4 && req.status === 200)
        {
            if (options.dataType == "json")
            {
                options.success(JSON.parse(req.response));
            }
            else
            {
                options.success(eval("(" + req.response + ")"));
            }
        }
    };

    // open the XMLHttpRequest connection
    req.open(options.type, options.url, true);

    if (options.type.toLowerCase() == "post")
    {
        for (var x in options.data)
        {
            arrayParams[paramsCount] = x + "=" + options.data[x];
            paramsCount++;
        }

        req.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        req.send(arrayParams.join("&"));
    }
    else
    {
        // send the XMLHttpRequest request (nothing has actually been sent until this very line)
        req.send();
    }
}