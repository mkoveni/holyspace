import java.time.*;
import java.util.UUID;
import org.onechurch.communities.application.CommunityHash;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.Membership;
import org.onechurch.events.application.contract.CommunityCalendar;

/**
 * Prints the Java-captured vectors in tests/Communities/Application/CommunityHashTest.php. From backend-java, with a JDK
 * and a compiled target/classes:
 *   javac -cp target/classes -d /tmp/v ../backend-symfony/docs/porting/vectors/CommunityHashVectors.java
 *   java -cp target/classes:/tmp/v CommunityHashVectors
 */
public class CommunityHashVectors {
    public static void main(String[] a) {
        UUID actor = UUID.fromString("11111111-1111-4111-8111-111111111111");
        UUID community = UUID.fromString("22222222-2222-4222-8222-222222222222");
        UUID person = UUID.fromString("33333333-3333-4333-8333-333333333333");
        UUID membership = UUID.fromString("44444444-4444-4444-8444-444444444444");
        UUID account = UUID.fromString("55555555-5555-4555-8555-555555555555");
        UUID contact = UUID.fromString("66666666-6666-4666-8666-666666666666");
        UUID venue = UUID.fromString("77777777-7777-4777-8777-777777777777");
        UUID parent = UUID.fromString("88888888-8888-4888-8888-888888888888");
        System.out.println("create " + CommunityHash.of(actor, "CREATE", "GROUP", "Home group", "Grüße 漢字", parent, "North"));
        System.out.println("createNulls " + CommunityHash.of(actor, "CREATE", "MINISTRY", "Youth", null, null, null));
        System.out.println("join " + CommunityHash.of(actor, "JOIN", community, person));
        System.out.println("appoint " + CommunityHash.of(actor, "APPOINT", membership, 3L, "LEADER", account, "Leads Tuesdays"));
        System.out.println("appointNulls " + CommunityHash.of(actor, "APPOINT", membership, 0L, "ASSISTANT", null, null));
        System.out.println("followup " + CommunityHash.of(actor, "FOLLOWUP", community, person, "Call after visit", LocalDate.of(2026, 3, 9)));
        CommunityCalendar.Schedule s1 = new CommunityCalendar.Schedule(venue, "Africa/Johannesburg", "WEEKLY", 1, 6, LocalDate.of(2026, 3, 3), LocalTime.of(18, 30), LocalTime.of(20, 0), 0);
        CommunityCalendar.Schedule s2 = new CommunityCalendar.Schedule(venue, "UTC", "MONTHLY", 2, 3, LocalDate.of(2026, 12, 31), LocalTime.of(23, 0, 15), LocalTime.of(1, 0, 0, 500_000_000), 1);
        System.out.println("scheduleText1 " + s1);
        System.out.println("scheduleText2 " + s2);
        System.out.println("schedule " + CommunityHash.of(actor, "SCHEDULE", community, 4L, s1, "abc123"));
        System.out.println("scheduleFraction " + CommunityHash.of(actor, "SCHEDULE", community, 0L, s2, "def456"));
        Membership m = new Membership(membership, community, person, Instant.parse("2026-01-01T00:00:00Z"), null, 2L);
        Community c = new Community(community, "GROUP", "Home group", null, null, null, true, 7L);
        System.out.println("review " + CommunityMessaging.hash(m, c, contact, "ada@example.org", "A note from Home group", "<p>Hello Ada</p>\n<p>Grüße</p>", 1L));
        System.out.println("approve " + CommunityHash.of(actor, membership, contact, "  Hello Ada  ", "f00d", "Checked by phone"));
    }
}
