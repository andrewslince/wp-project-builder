function WPProjectBuilder()
{
    this.packageListElement = null;

    this.qttSelectedPackages = 0;

    this.currentDownloadPackage = 0;

    this.packageList = [];

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
            btnAddPluginClassList.remove("inactive");
        }
        else
        {
            btnAddPluginClassList.add("inactive");
        }
    };

    this.addNewPlugin = function()
    {
        var doc                       = document,
            sectionPluginConfig       = doc.getElementById("build-config-plugins"),
            divBuildContainer         = doc.getElementById("build-container"),
            divOverlay                = doc.createElement("div"),
            referenceId               = "adding-plugin",
            classMessage              = "",
            htmlOutput                = "";

        if (WPProjectBuilder.newPluginIsValid())
        {
            htmlOutput += "<div class=\"loading-layer-icon\"></div>";
            htmlOutput += "<div class=\"loading-layer-message\">adicionando plugin...</div>";
            htmlOutput += "<a href=\"javascript:void(0);\" class=\"btn btn-primary close-loading-layer\" onclick=\"WPProjectBuilder.closeOverlay('" + referenceId + "'); document.querySelector('#build-config-plugins .accordion-menu-content').style.display = 'block';\">voltar</a>";

            divOverlay.setAttribute("id", referenceId);
            divOverlay.setAttribute("class", "loading-layer-container");
            divOverlay.innerHTML = htmlOutput;

            // hide plugin list
            doc.querySelector("#build-config-plugins .accordion-menu-content").style.display = "none";

            // show message "adding plugin..."
            sectionPluginConfig.appendChild(divOverlay);

            divBuildContainer.classList.add(referenceId);

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
                    
                    WPProjectBuilder.loadPluginList();

                    // mostrar mensagem do build gerado com sucesso
                    doc.getElementById(referenceId).classList.add(classMessage);
                    doc.querySelector("#" + referenceId + " .loading-layer-message").innerHTML = response.message;

                    // display back button
                    doc.querySelector("#" + referenceId + " .close-loading-layer").style.display = "block";

                    // display build project button
                    divBuildContainer.classList.remove(referenceId);

                    // reset form plugin states
                    doc.getElementById("new-plugin-url").value = "";
                    doc.getElementById("new-plugin-submit").setAttribute("class", "inactive");
                }
            });
        }

        return false;
    };

    this.closeOverlay = function(referenceId)
    {
        var divOverlay = document.querySelector("#" + referenceId + ".loading-layer-container");
        divOverlay.parentNode.removeChild(divOverlay);
    };

    this.build = function()
    {
        var doc                    = document,
            coreOptions            = doc.querySelectorAll(".single-option .option-description"),
            pluginOptions          = doc.querySelectorAll(".multiple-options .option-description"),
            divBuildContainer      = doc.getElementById("build-container"),
            divOverlay             = doc.createElement("div"),
            qttSelectedPlugins     = pluginOptions.length,
            qttSelectedCore        = coreOptions.length,
            htmlPackages           = "",
            htmlOutput             = "",
            coreList               = [],
            pluginList             = [],
            i                      = 0,
            j                      = 0,
            counterSelectedCore    = 0,
            counterSelectedPlugins = 0;

        // selected wp core
        for (i = 0; i < qttSelectedCore; i++)
        {
            if (coreOptions[i].classList.contains("selected"))
            {
                htmlPackages += "<li data-package-name=\"" + coreOptions[i].innerHTML + "\" class=\"download-pending\">" + coreOptions[i].innerHTML + "</li>";
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
                htmlPackages += "<li data-package-name=\"" + pluginList[counterSelectedPlugins] + "\" class=\"download-pending\">" + pluginList[counterSelectedPlugins] + "</li>";
                counterSelectedPlugins++;
            }
        }

        htmlPackages += "<li class=\"download-pending\">empacotando projeto</li>";

        htmlOutput += "<div class=\"loading-layer-icon\"></div>";
        htmlOutput += "<div class=\"loading-layer-message\">gerando build do projeto...</div>";
        htmlOutput += "<a href=\"javascript:void(0);\" id=\"download-build-link\"></a>";
        htmlOutput += "<div class=\"loading-layer-content\">";
        htmlOutput += "    <p class=\"description\">baixando pacotes selecionados</p>";
        htmlOutput += "    <ul id=\"downloading-selected-plugins\">" + htmlPackages + "</ul>";
        htmlOutput += "</div>";

        divOverlay.setAttribute("id", "loading-build");
        divOverlay.setAttribute("class", "loading-layer-container");
        divOverlay.innerHTML = htmlOutput;

        divBuildContainer.appendChild(divOverlay);
        divBuildContainer.classList.add("generating-build");

        WPProjectBuilder.manageDownloadPackages(coreList, pluginList);
    };

    this.manageDownloadPackages = function(core, plugins)
    {
        WPProjectBuilder.packageListElement  = document.querySelectorAll("#downloading-selected-plugins li");
        WPProjectBuilder.packageList         = core.concat(plugins);
        WPProjectBuilder.qttSelectedPackages = WPProjectBuilder.packageList.length;

        WPProjectBuilder.downloadPackage(0);
    };

    this.downloadPackage = function(currentPackage)
    {
        var doc             = document,
            buildLink       = doc.getElementById("download-build-link"),
            pckg            = WPProjectBuilder.packageListElement[currentPackage],
            classListPckg   = pckg.classList,
            divLoadingBuild = doc.getElementById("loading-build");
        
        classListPckg.remove("download-pending");
        classListPckg.add("download-in-progress");

        if (currentPackage < WPProjectBuilder.qttSelectedPackages)
        {
            ajax({
                url  : "action/download-package",
                type : "POST",
                dataType : "json",
                data : {
                    name : pckg.getAttribute("data-package-name"),
                    type : ((currentPackage === 0) ? "core" : "plugin")
                },
                success : function (response)
                {
                    classListPckg.remove("download-in-progress");
                    classListPckg.add("download-" + ((response) ? "finished" : "error"));

                    WPProjectBuilder.downloadPackage((parseInt(currentPackage) + 1));
                }
            });
        }
        else
        {
            ajax({
                url  : "action/packaging-build",
                type : "GET",
                dataType : "json",
                success : function (response)
                {
                    classListPckg.remove("download-in-progress");
                    classListPckg.add("download-" + ((response) ? "finished" : "error"));

                    // mostrar link para download do build
                    buildLink.href          = "action/download-build?build_id=" + response.data.buildId;
                    buildLink.style.display = "block";

                    // mostrar mensagem do build gerado com sucesso
                    divLoadingBuild.classList.add("success");
                    doc.querySelector("#loading-build .loading-layer-message").innerHTML = "build gerado com sucesso!";
                }
            });
        }
    };
}

var WPProjectBuilder = new WPProjectBuilder();

WPProjectBuilder.init();