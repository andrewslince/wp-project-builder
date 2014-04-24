function WPProjectBuilder()
{
    this.init = function()
    {
        WPProjectBuilder.validateActiveBuildAction();
        WPProjectBuilder.validateActiveNewPluginAction();
        WPProjectBuilder.loadPluginList();
    };

    this.loadPluginList = function()
    {
        var doc = document,
            divPluginList = doc.getElementById("plugin-list"),
            htmlOutput = "",
            qttPlugins = 0,
            i          = 0;

        divPluginList.innerHTML = "carregando plugins...";

        ajax({
            url  : "action/get-plugin-list",
            type : "GET",
            dataType : "json",
            success : function (response)
            {
                qttPlugins = response.length;

                htmlOutput = "<ul class=\"choice-options multiple-options\">";
                for (i = 0; i < qttPlugins; i++)
                {
                    htmlOutput += "<li>";
                    htmlOutput += "    <a class=\"option-description\" data-plugin-name=\"" + response[i].name + "\" onclick=\"WPProjectBuilder.selectOption(this);\" href=\"javascript:void(0);\">" + response[i].title + "</a>";
                    htmlOutput += "    <a target=\"_blank\" href=\"https://wordpress.org/plugins/" + response[i].name + "/\" class=\"plugin-info\">i</a>";
                    htmlOutput += "</li>";
                }
                htmlOutput += "</ul>";

                divPluginList.innerHTML = htmlOutput;
            }
        });
    };

    this.selectOption = function(element)
    {
        var ul          = element.parentNode.parentNode,
            ulChildren  = ul.children,
            qttChildren = ulChildren.length,
            i           = 0;

        element.classList.toggle("selected");

        // single option
        if (ul.classList.contains("single-option"))
        {
            for (i = 0; i < qttChildren; i++)
            {
                if (ulChildren[i].firstElementChild != element)
                {
                    ulChildren[i].firstElementChild.classList.remove("selected");
                }
            }
        }

        WPProjectBuilder.validateActiveBuildAction();
    };

    this.validateActiveBuildAction = function()
    {
        var doc                      = document,
            btnBuildProject          = doc.getElementById("btn-build-project"),
            btnBuildProjectClassList = btnBuildProject.classList,
            coreOptions              = doc.querySelectorAll("#build-config-core a"),
            qttCoreOptions           = coreOptions.length,
            selectedCore             = false,
            i                        = 0;

        // checks if a wp core has been selectedchecks if a wp core has selected
        for (i = 0; i < qttCoreOptions; i++)
        {
            if (coreOptions[i].classList.contains("selected"))
            {
                selectedCore = true;
                break;
            }
        }

        if (selectedCore)
        {
            btnBuildProject.setAttribute("onclick", "WPProjectBuilder.build();");
            btnBuildProject.setAttribute("title", "clique para gerar o build do seu projeto");
            btnBuildProjectClassList.remove("btn-inactive");
        }
        else
        {
            btnBuildProject.removeAttribute("onclick");
            btnBuildProject.setAttribute("title", "escolha uma versão do core do wp");
            btnBuildProjectClassList.add("btn-inactive");
        }
    };

    this.newPluginIsValid = function()
    {
        return (document.getElementById("new-plugin-url").value !== "") ? true : false;
    };

    this.validateActiveNewPluginAction = function()
    {
        var doc                   = document,
            btnAddPluginClassList = doc.getElementById("new-plugin-submit").classList;

        if (WPProjectBuilder.newPluginIsValid())
        {
            btnAddPluginClassList.add("active");
            btnAddPluginClassList.remove("inactive");
        }
        else
        {
            btnAddPluginClassList.add("inactive");
            btnAddPluginClassList.remove("active");
        }
    };

    this.addNewPlugin = function()
    {
        var doc                  = document,
            sectionPluginOptions = doc.getElementById("build-config-plugins"),
            divOverlay           = doc.createElement("div"),
            stepsMessage         = null,
            classMessage         = "";

        if (WPProjectBuilder.newPluginIsValid())
        {
            divOverlay.innerHTML = "<div id=\"msg-steps-add-new-plugin\">adicionando plugin... aguarde...</div><a href=\"javascript:void(0);\" id=\"close-overlay\" class=\"btn btn-primary\" onclick=\"WPProjectBuilder.closeOverlay();\">voltar</a>";
            divOverlay.setAttribute("class", "overlay-layer");
            sectionPluginOptions.appendChild(divOverlay);

            stepsMessage = doc.getElementById("msg-steps-add-new-plugin");
            
            ajax({
                url  : "action/add-new-plugin",
                type : "POST",
                dataType : "json",
                data : {
                    url : doc.getElementById("new-plugin-url").value
                },
                success : function (response)
                {
                    if (response.statusCode == 1)
                    {
                        WPProjectBuilder.loadPluginList();
                        classMessage = "success";
                    }
                    else
                    {
                        classMessage = "error";
                    }

                    // update messaage
                    divOverlay.classList.add(classMessage);
                    stepsMessage.setAttribute("class", classMessage);
                    stepsMessage.innerHTML = response.message;

                    // display back button
                    doc.getElementById("close-overlay").style.display = "block";

                }
            });
        }

        return false;
    };

    this.closeOverlay = function()
    {
        var doc = document,
            divOverlay = document.getElementsByClassName("overlay-layer")[0];

        doc.getElementById("new-plugin-url").value = "";
        doc.getElementById("new-plugin-submit").setAttribute("class", "inactive");

        divOverlay.parentNode.removeChild(divOverlay);
    };

    this.build = function()
    {
        var doc                    = document,
            coreOptions            = doc.querySelectorAll(".single-option .option-description"),
            pluginOptions          = doc.querySelectorAll(".multiple-options .option-description"),
            divOverlay             = doc.createElement("div"),
            linkDownload           = doc.createElement("a"),
            qttSelectedPlugins     = pluginOptions.length,
            qttSelectedCore        = coreOptions.length,
            classMessage           = "",
            coreList               = [],
            pluginList             = [],
            i                      = 0,
            j                      = 0,
            counterSelectedCore    = 0,
            counterSelectedPlugins = 0;

        divOverlay.innerHTML = "<div id=\"loading-build-container\"><p id=\"build-main-message\">gerando build do projeto...</p></div><a href=\"javascript:void(0);\" id=\"close-overlay-build\" onclick=\"WPProjectBuilder.closeOverlay();\">voltar</a>";
        divOverlay.setAttribute("class", "overlay-layer");
        doc.getElementById("build-container").appendChild(divOverlay);

        // selected wp core
        for (i = 0; i < qttSelectedCore; i++)
        {
            if (coreOptions[i].classList.contains("selected"))
            {
                coreList[counterSelectedCore] = coreOptions[i].innerHTML;
                counterSelectedCore++;
            }
        }

        // selected wp plugins
        for (j = 0; j < qttSelectedPlugins; j++)
        {
            if (pluginOptions[j].classList.contains("selected"))
            {
                pluginList[counterSelectedPlugins] = pluginOptions[j].getAttribute("data-plugin-name");
                counterSelectedPlugins++;
            }
        }

        ajax({
            url  : "action/build-project",
            type : "POST",
            dataType : "json",
            data : {
                core : coreList.join(","),
                plugins : pluginList.join(",")
            },
            success : function (buildId)
            {
                if (buildId !== "")
                {
                    linkDownload.href = "action/download-build?build_id=" + buildId;
                    linkDownload.setAttribute("id", "link-download-build");
                    doc.getElementById("loading-build-container").appendChild(linkDownload);
                    

                    classMessage = "success";
                }
                else
                {
                    classMessage = "error";
                }

                // update messaage
                divOverlay.classList.add(classMessage);
                doc.getElementById("build-main-message").setAttribute("class", classMessage);
                doc.getElementById("build-main-message").innerHTML = "build gerado com sucesso!";

                // display back button
                doc.getElementById("close-overlay-build").style.display = "block";
            }
        });
    };
}

var WPProjectBuilder = new WPProjectBuilder();