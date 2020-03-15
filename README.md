# php-mysql-replication
A library to create MySQL Database replication.


# Features
* Cloning database structure (even columns order)
* Cloning all indexes
* Cloning all data


# Why not use a default built-in replication methods
The default replication has some weak points:
* It creates a big binlog file (if you have a big database) - so you need a large amount of disk space to handle those binlogs
* It does not supports the triggers - triggers will be saved as SQL-query in binlog, but if you want to have a copy of just a few tables - some data will be incorrect.


# How to setup
1. If you want to replicate on the same server:  
  1. You can create a worker that will do the job for example every minute (examples/worker.php)
It can be setted up with crontab.  
  2. You can create a daemon that will work always and do the job every n seconds (examples/daemon.php)
2. If you want to replicate on another server:  
On secondary server (Linux):
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```
Navigate to the line that begins with the bind-address directive. By default, this value is set to 127.0.0.1, meaning that the server will only look for local connections. You will need to change this directive to reference an external IP address. For the purposes of troubleshooting, you could set this directive to a wildcard IP address, either *, ::, or 0.0.0.0:  
```
bind-address            = 0.0.0.0
```
After changing this line, save and close the file (CTRL + X, Y, then ENTER if you edited it with nano).

Assuming you’ve configured a firewall on your database server, you will also need to open port 3306 — MySQL’s default port — to allow traffic to MySQL.
If you only plan to access the database server from one specific machine, you can grant that machine exclusive permission to connect to the database remotely with the following command. Make sure to replace remote_IP_address with the actual IP address of the machine you plan to connect with:
```bash
sudo ufw allow from REMOTE_IP_ADDRESS to any port 3306
```

Alternatively, you can allow connections to your MySQL database from any IP address with the following command:
```bash
sudo ufw allow 3306
```

Lastly, restart the MySQL service to put the changes you made to mysqld.cnf into effect:
```bash
sudo systemctl restart mysql
```

MySQL setup guide source: https://www.digitalocean.com/community/tutorials/how-to-allow-remote-access-to-mysql


# License
This library licensed under MIT.
