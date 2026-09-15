<?php
namespace Tests\Feature;
use Symfony\Component\Process\Process;
use Tests\TestCase;
class SalonBookingConcurrencyTest extends TestCase {
    public function test_simultaneous_requests_cannot_double_book_a_stylist(): void {
        $dir=storage_path('framework/testing');if(!is_dir($dir))mkdir($dir,0777,true);
        $database=$dir.'/salon-booking-'.bin2hex(random_bytes(8)).'.sqlite';touch($database);
        $env=['APP_ENV'=>'testing','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,'DB_URL'=>false,'CACHE_STORE'=>'array'];$workers=[];
        try {
            (new Process([PHP_BINARY,'tests/Support/salon-booking-worker.php','setup'],base_path(),$env,null,60))->mustRun();
            foreach(['1','2'] as $id){$workers[]=$p=new Process([PHP_BINARY,'tests/Support/salon-booking-worker.php',$id],base_path(),$env,null,30);$p->start();}
            $deadline=microtime(true)+15;while(!file_exists($database.'.ready1')||!file_exists($database.'.ready2')){if(microtime(true)>$deadline)$this->fail('Workers did not start.');usleep(20000);}
            touch($database.'.go');$results=[];foreach($workers as $p){$p->wait();$this->assertTrue($p->isSuccessful(),$p->getOutput().$p->getErrorOutput());$results[]=trim($p->getOutput());}
            sort($results);$this->assertSame(['CONFLICT','CREATED'],$results);
            $connection=new \PDO('sqlite:'.$database);$this->assertSame(1,(int)$connection->query('SELECT COUNT(*) FROM salon_appointments')->fetchColumn());$this->assertSame(1,(int)$connection->query('SELECT appointment_sequence FROM tenants')->fetchColumn());$connection=null;
        }finally{foreach($workers as $p)if($p->isRunning())$p->stop();foreach(['','.fixture','.ready1','.ready2','.go','-journal','-wal','-shm']as$suffix)if(file_exists($database.$suffix))unlink($database.$suffix);}
    }
}
