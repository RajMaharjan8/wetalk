import GroupsIcon from "@mui/icons-material/Groups";

interface GroupTabProps {
  name: string;
  memberCount: number;
  lastMessage?: string;
  lastSenderName?: string;
  isActive: boolean;
  onClick: () => void;
}

export default function GroupTab({
  name,
  memberCount,
  lastMessage,
  lastSenderName,
  isActive,
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
      <div className="text-gray-600 min-w-0">
        <h3 className="font-semibold truncate">{name}</h3>
        {lastMessage ? (
          <p className="font-light text-sm text-gray-500 line-clamp-1 break-all">
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
