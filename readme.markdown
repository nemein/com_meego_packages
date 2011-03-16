## Overview

Todo..

## Terminology

* Project

  Projects hosted at the OBS farm. Projects hold repositoroies.

* Top project

  Top project is a project that hosts repositories of packages that are meant
  to be downloaded by device users (mainly non-techie audience).

  Top projects are configured by the administrators.

* Repository

  Repositories are within projects at the OBS farm. Repositories host packages.

* Package

  All the rpm or deb packages that are imported from OBS repositories.

* Application

  An application is a executable program that is delivered by one (or more) package(s).

* Package Category

  Each package specifies a category where it belongs to.

* Base Category

  Since there are no limit of package categories we defined base categories that
  map all the package categories. One or many package category belongs to a base category.

  Base categories are used in the UI of MeeGo Apps. If there is no base category defined
  then the application revert to package categories.

