<?php
declare(strict_types=1);
namespace App\IdentityAccess\Infrastructure\Persistence\DBAL;
use App\IdentityAccess\Domain\Model\Permission; use App\IdentityAccess\Domain\Repository\PermissionRepository; use App\IdentityAccess\Domain\ValueObject\PermissionId; use App\IdentityAccess\Domain\ValueObject\RoleId; use Doctrine\DBAL\Connection;
final readonly class DbalPermissionRepository implements PermissionRepository {
 public function __construct(private Connection $connection,private PermissionMapper $mapper){}
 public function save(Permission $p):void{$d=$this->mapper->toRow($p);$exists=$this->connection->fetchOne('SELECT 1 FROM permissions WHERE id=:id',['id'=>$p->id()->toString()]);if($exists){unset($d['id']);$this->connection->update('permissions',$d,['id'=>$p->id()->toString()]);}else{$this->connection->insert('permissions',$d);}}
 public function findById(PermissionId $id):?Permission{$r=$this->connection->fetchAssociative('SELECT * FROM permissions WHERE id=:id',['id'=>$id->toString()]);return $r===false?null:$this->mapper->toDomain($r);}
 public function findByCode(string $code):?Permission{$r=$this->connection->fetchAssociative('SELECT * FROM permissions WHERE code=:code',['code'=>trim($code)]);return $r===false?null:$this->mapper->toDomain($r);}
 public function findForRole(RoleId $roleId):array{$rows=$this->connection->fetchAllAssociative('SELECT p.* FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id=p.id WHERE rp.role_id=:role_id ORDER BY p.code',['role_id'=>$roleId->toString()]);return array_map(fn(array $row):Permission=>$this->mapper->toDomain($row),$rows);}
 public function findAll():array{$rows=$this->connection->fetchAllAssociative('SELECT * FROM permissions ORDER BY code');return array_map(fn(array $row):Permission=>$this->mapper->toDomain($row),$rows);}
}
