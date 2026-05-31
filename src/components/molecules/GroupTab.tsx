import GroupsIcon from "@mui/icons-material/Groups";

interface GroupTabProps {
  name: string;
  memberCount: number;
  lastMessage?: string;
  lastSenderName?: string;
  isActive: boolean;
  unread?: boolean;
  onClick: () => void;
}

export default function GroupTab({
  name,
  memberCount,
  lastMessage,
  lastSenderName,
  isActive,
  unread,
  onClick,
}: GroupTabProps) {
  return (
    <div
      className={`w-full min-h-16 flex items-center px-4 py-2 gap-4 justify-start cursor-pointer hover:bg-light-text transition-all ease-in-out ${
        isActive ? "bg-light-text" : ""
      }`}
      onClick={onClick}
    >
      {/* group avatar */}
      <div className="h-14 w-14 shrink-0 rounded-full bg-primary flex justify-center items-center text-white">
        <GroupsIcon />
      </div>
      <div className="text-gray-600 min-w-0 flex-1">
        <h3 className={`truncate flex items-center gap-2 ${unread ? "font-bold text-gray-900" : "font-semibold"}`}>
          <span className="truncate">{name}</span>
          {unread && <span className="h-2.5 w-2.5 rounded-full bg-primary shrink-0" />}
        </h3>
        {lastMessage ? (
          <p
            className={`text-sm line-clamp-1 break-all ${
              unread ? "font-semibold text-gray-900" : "font-light text-gray-500"
            }`}
          >
            {lastSenderName ? `${lastSenderName.split(" ")[0]}: ` : ""}
            {lastMessage}
          </p>
        ) : (
          <span className="font-light text-sm text-gray-500">
            {memberCount} {memberCount === 1 ? "member" : "members"}
          </span>
        )}
      </div>
    </div>
  );
}
