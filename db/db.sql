/*
### -- Vulnerabilidad 1: Es que se esta dejando archivos dentro del archivo de la base de datos porque se va a subir a un repositorio
Nombre de la Base de datos::
create database webapps;

Privilegios::
GRANT ALL PRIVILEGES ON webapps.* TO 'udbwebaps'@'localhost' IDENTIFIED BY 'OPY@$j9G2<hJ';
FLUSH PRIVILEGES

*/

use webapps;

create table if not exists Usuarios(
	id int AUTO_INCREMENT, 
	uname varchar(50) not null, 
	email varchar(250) not null, 
	password varchar(250) not null,
	PRIMARY KEY (id)
)ENGINE=InnoDB;

create table if not exists AccesoToken(
	id_Usuario int primary key, 
	token varchar(250) not null, 
	fecha datetime not null
)ENGINE=InnoDB;

create table if not exists Imagen(
	id int AUTO_INCREMENT, 
	name varchar(250) not null, 
	ruta text not null,
	id_Usuario int not null,
	PRIMARY KEY (id)
)ENGINE=InnoDB;

/*contrain corregido*/
ALTER TABLE Usuarios ADD CONSTRAINT U_U Unique (uname,email);

ALTER TABLE AccesoToken ADD CONSTRAINT FK_ATU FOREIGN KEY (id_Usuario) REFERENCES Usuarios(id);

ALTER TABLE Imagen ADD CONSTRAINT FK_I_U FOREIGN KEY (id_Usuario) REFERENCES Usuarios(id);
