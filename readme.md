# TSMap

Project for the course "COMP130004h - Data Structure" at Fudan University, 2024 Fall.

Sub repos for the project:

-   Backend #1, main part of the project, written in C++, implementing the data structure and the algorithm
-   Backend #2, written in PHP, providing supporting services for the frontend (e.g. Node saving)
-   Frontend, written in Vue.js, providing the user interface for the project
-   **Renderer**, written in PHP, providing SVG rendering for each tile in the map

# Renderer

## License

This project is licensed under the GNU General Public License v3.0.

**You are free to use, modify, and distribute this project, as long as you follow the license.**

If you are a student in Fudan University: **As the rules and regulations of Fudan University, you are not allowed to copy anything from this repo in your project in the same course.**

## To develop

Requirements: PHP, nginx, PostgreSQL, PostGIS, osm2pgsql

Install PHP and nginx and configure CGI support, follow this guide:

https://www.php.net/manual/en/install.php

Install PostgreSQL and PostGIS plugin, follow this guide:

https://postgis.net/documentation/getting_started/#installing-postgis

Import map data using osm2pgsql, follow this guide:

https://osm2pgsql.org/doc/manual.html

Put php-svg in.

# TSMap

复旦大学 2024-2025 秋季学期《数据结构》课程项目。

项目的子仓库：

-   后端 #1，项目的主要部分，使用 C++ 编写，实现数据结构和算法
-   后端 #2，使用 PHP 编写，为前端提供支持服务（例如节点保存）
-   前端，使用 Vue.js 编写，为项目提供用户界面
-   **渲染器**，使用 PHP 编写，为地图中的每个瓦片提供 SVG 渲染

# 渲染器

## 许可证

本项目使用 GNU 通用公共许可证 v3.0 进行许可。

**您可以自由使用、修改和分发本项目，只要您遵守许可证即可。**

如果您是复旦大学的学生：**根据复旦大学的规定，您不得在同一课程的项目中从此存储库中复制任何内容。**

## 开发

环境要求：PHP，nginx，PostgreSQL，PostGIS，osm2pgsql

安装 PHP、nginx 并配置 CGI 支持，依据此说明：

https://www.php.net/manual/zh/install.php

安装 PostgreSQL 和 PostGIS 插件，依据此说明：

https://postgis.net/documentation/getting_started/#installing-postgis

使用 osm2pgsql 导入地图数据，依据此说明：

https://osm2pgsql.org/doc/manual.html

将 php-svg 放入。